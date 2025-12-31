<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PortfolioProject;
use App\Models\PortfolioSkill;
use App\Models\PortfolioExperience;
use App\Models\PortfolioProfile;
use App\Models\Conversation;
use App\Models\User;
use App\Services\PortfolioExportService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Enums\Role;
use Gemini\Data\Content;

use App\Models\AppSetting;

class AiController extends Controller
{
    private function configureGemini()
    {
        $setting = AppSetting::where('key', 'gemini_api_key')->first();
        if ($setting && $setting->value) {
            config(['gemini.api_key' => $setting->value]);
        }
    }

    public function config(Request $request)
    {
        $setting = AppSetting::where('key', 'gemini_api_key')->first();
        $gemini = (bool) ($setting ? $setting->value : config('gemini.api_key'));

        return response()->json([
            'gemini_configured' => $gemini,
        ]);
    }

    // Backend endpoint to update API Key
    public function updateConfig(Request $request)
    {
        $request->validate([
            'gemini_api_key' => 'required|string',
        ]);

        $key = $request->input('gemini_api_key');

        AppSetting::updateOrCreate(
            ['key' => 'gemini_api_key'],
            ['value' => $key]
        );

        return response()->json(['message' => 'Gemini API Key updated successfully']);
    }

    public function chat(Request $request)
    {
        $this->configureGemini();
        $userId = $request->user()->id;
        $message = (string) $request->input('message');
        $convId = (int) $request->input('conversation_id');
        if ($message === '') return response()->json(['error' => 'Empty message'], 422);

        $conv = null;
        if ($convId) $conv = Conversation::where('user_id', $userId)->where('id', $convId)->first();
        if (!$conv) $conv = Conversation::create(['user_id' => $userId, 'messages' => []]);

        // 1. Full Context Injection (Replacing Pinecone RAG)
        $projects = PortfolioProject::all(['name', 'description', 'tech_stack'])->toArray();
        $skills = PortfolioSkill::all(['name', 'level'])->toArray();
        $experiences = PortfolioExperience::all(['company', 'title', 'start_date', 'end_date', 'description'])->toArray();
        $profile = PortfolioProfile::first(['headline', 'summary', 'location', 'email_public', 'website_url', 'github_url', 'linkedin_url']);

        $contextData = [
            'profile' => $profile ? $profile->toArray() : null,
            'projects' => $projects,
            'skills' => $skills,
            'experience' => $experiences,
        ];

        // 2. Prepare System Instruction
        $systemPrompt = "You are the Johnrak AI Assistant, a digital representative of the developer Vorak.\n" .
            "Data Source: You have access to Vorak's complete portfolio data below. Always prioritize this data.\n" .
            "Persona: Be technical, concise, and professional.\n" .
            "Constraints: If asked about something not in the provided context, say 'I haven't added that experience to my database yet.' Do not hallucinate.\n" .
            "Style: Use Markdown for structure. Use bold text for project names. Keep responses under 150 words.\n\n" .
            "[FULL CONTEXT DATA]: " . json_encode($contextData);

        // 3. Prepare History
        $history = is_array($conv->messages) ? $conv->messages : [];
        $geminiHistory = [];
        // Last 6 messages (3 exchanges)
        foreach (array_slice($history, max(0, count($history) - 6)) as $msg) {
            // Map 'assistant' to 'model'
            $role = ($msg['role'] === 'user') ? Role::USER : Role::MODEL;
            // Ensure content is not empty
            if (!empty($msg['content'])) {
                $geminiHistory[] = Content::parse(part: $msg['content'], role: $role);
            }
        }

        // 4. Call Gemini
        $answer = '';
        try {
            // Using gemini-flash-latest as it is the stable model available for the current plan
            $response = Gemini::generativeModel('gemini-flash-latest')
                ->withSystemInstruction(Content::parse(part: $systemPrompt))
                ->startChat(history: $geminiHistory)
                ->sendMessage($message);

            $answer = $response->text();
        } catch (\Throwable $e) {
            Log::error("Gemini chat error: " . $e->getMessage());
            $answer = "Vorak's AI is resting. Reason: " . $e->getMessage();
        }

        // 5. Save Conversation
        $newHistory = array_merge($history, [
            ['role' => 'user', 'content' => $message],
            ['role' => 'assistant', 'content' => $answer], // Store as assistant for internal consistency
        ]);
        // Keep last 20 messages
        $conv->messages = array_values(array_slice($newHistory, -20));
        $conv->save();

        return response()->json([
            'conversation_id' => $conv->id,
            'assistant' => $answer,
        ]);
    }

    public function publicChat(Request $request)
    {
        $this->configureGemini();
        $message = (string) $request->input('message');
        $clientHistory = $request->input('history', []);

        if ($message === '') return response()->json(['error' => 'Empty message'], 422);

        // Special Command: /sync
        if (trim($message) === '/sync') {
            return response()->json([
                'assistant' => "Please enter your **Portfolio Sync Token** (generated in Admin Panel).",
            ]);
        }

        // Check if previous message was asking for Token or OTP
        $lastAiMessage = null;
        if (!empty($clientHistory)) {
            $lastItem = end($clientHistory);
            if ($lastItem['role'] === 'assistant') {
                $lastAiMessage = $lastItem['content'];
            }
        }

        // Step 2: Validate Token
        if ($lastAiMessage === "Please enter your **Portfolio Sync Token** (generated in Admin Panel).") {
            $token = trim($message);
            // Hash token to verify existence in cache
            $hash = hash('sha256', $token);
            $entry = \Illuminate\Support\Facades\Cache::get("portfolio_sync_token:{$hash}");

            if (!$entry) {
                return response()->json([
                    'assistant' => "❌ **Invalid or expired token.**\n\nPlease generate a new token in the Admin Panel and try `/sync` again.",
                ]);
            }

            // Token is valid, ask for OTP
            // We can't easily store the token in session here since it's stateless public API.
            // But we can ask the user to provide it again? No that's bad UX.
            // We will trust the client to send the history, and we will look back at this message later.
            return response()->json([
                'assistant' => "✅ Token accepted.\n\nPlease enter your **6-digit MFA Code** from your authenticator app.",
            ]);
        }

        // Step 3: Validate OTP and Execute Sync
        if ($lastAiMessage === "✅ Token accepted.\n\nPlease enter your **6-digit MFA Code** from your authenticator app.") {
            $otp = trim($message);

            // Find the token in the history (2 messages back from user)
            // History: [User:/sync, AI:AskToken, User:TOKEN, AI:AskOTP] -> Current: OTP
            // So we need to look at the LAST user message in history
            $token = '';
            // Iterate backwards
            for ($i = count($clientHistory) - 1; $i >= 0; $i--) {
                if ($clientHistory[$i]['role'] === 'user') {
                    $token = trim($clientHistory[$i]['content']);
                    break;
                }
            }

            if (!$token) {
                return response()->json(['assistant' => "❌ Error: Could not find token in conversation history. Please restart with `/sync`."]);
            }

            // Verify Token Again
            $hash = hash('sha256', $token);
            $entry = \Illuminate\Support\Facades\Cache::get("portfolio_sync_token:{$hash}");

            if (!$entry) {
                return response()->json(['assistant' => "❌ **Token expired.** Please try `/sync` again."]);
            }

            $user = User::find($entry['user_id'] ?? 0);
            if (!$user) return response()->json(['assistant' => "❌ Invalid token owner."]);

            // Verify OTP
            $secret = $user->getTwoFactorSecretDecrypted();
            if (!$secret) return response()->json(['assistant' => "❌ 2FA not enabled on admin account."]);

            $g2fa = new \PragmaRX\Google2FA\Google2FA();
            // Allow window of 1 (30 seconds before/after)
            if (!$g2fa->verifyKey($secret, $otp, 1)) {
                return response()->json(['assistant' => "❌ **Invalid OTP Code.** Please try entering the code again."]);
            }

            // Execute Sync
            try {
                $service = app(PortfolioExportService::class);
                $data = $service->build($user->id);

                // Paths
                $profilePath = '/Users/apple/DEV/Project/Personal web/johnrak-portfolio/src/data/profile.json';
                $defaultPath = '/Users/apple/DEV/Project/Personal web/johnrak-portfolio/src/data/default-profile.json';

                // Ensure directory exists
                if (!file_exists(dirname($profilePath))) {
                    mkdir(dirname($profilePath), 0755, true);
                }

                // 1. Rename existing to default-profile.json (Backup)
                // Always overwrite default-profile.json with the current profile.json
                if (file_exists($profilePath)) {
                    copy($profilePath, $defaultPath);
                }

                // 2. Write new data
                file_put_contents($profilePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                return response()->json([
                    'assistant' => "✅ **Sync Successful!**\n\n- Latest data fetched from Admin.\n- Old profile saved as `default-profile.json`.\n- Portfolio updated.",
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'assistant' => "❌ Sync failed during file write: " . $e->getMessage(),
                ]);
            }
        }

        // 1. Full Context Injection
        $projects = PortfolioProject::all(['name', 'description', 'tech_stack'])->toArray();
        $skills = PortfolioSkill::all(['name', 'level'])->toArray();
        $experiences = PortfolioExperience::all(['company', 'title', 'start_date', 'end_date', 'description'])->toArray();
        $profile = PortfolioProfile::first(['headline', 'summary', 'location', 'email_public', 'website_url', 'github_url', 'linkedin_url']);

        $contextData = [
            'profile' => $profile ? $profile->toArray() : null,
            'projects' => $projects,
            'skills' => $skills,
            'experience' => $experiences,
        ];

        // 2. Prepare System Instruction
        $systemPrompt = "You are the Johnrak AI Assistant, a digital representative of the developer Vorak.\n" .
            "Data Source: You have access to Vorak's complete portfolio data below. Always prioritize this data.\n" .
            "Persona: Be technical, concise, and professional.\n" .
            "Constraints: If asked about something not in the provided context, say 'I haven't added that experience to my database yet.' Do not hallucinate.\n" .
            "Style: Use Markdown for structure. Use bold text for project names. Keep responses under 150 words.\n\n" .
            "[FULL CONTEXT DATA]: " . json_encode($contextData);

        // 3. Prepare History for Gemini
        $geminiHistory = [];
        // Use the client-provided history, limited to last 6 messages
        foreach (array_slice($clientHistory, max(0, count($clientHistory) - 6)) as $msg) {
            $role = ($msg['role'] === 'user') ? Role::USER : Role::MODEL;
            if (!empty($msg['content'])) {
                $geminiHistory[] = Content::parse(part: $msg['content'], role: $role);
            }
        }

        // 4. Call Gemini
        $answer = '';
        try {
            $response = Gemini::generativeModel('gemini-flash-latest')
                ->withSystemInstruction(Content::parse(part: $systemPrompt))
                ->startChat(history: $geminiHistory)
                ->sendMessage($message);

            $answer = $response->text();
        } catch (\Throwable $e) {
            Log::error("Gemini public chat error: " . $e->getMessage());
            $answer = "Vorak's AI is resting. Reason: " . $e->getMessage();
        }

        return response()->json([
            'assistant' => $answer,
        ]);
    }
}
