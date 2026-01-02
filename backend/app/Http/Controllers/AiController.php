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
use App\Models\Backup;
use Illuminate\Support\Facades\DB;

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

        // 1. Fetch Real-Time System Status (Admin Only)
        // Re-using logic from DashboardController to get health stats
        $dashboardController = app(\App\Http\Controllers\DashboardController::class);
        // We need to mock a request or extract the logic.
        // For simplicity, let's extract the logic into private helper or just duplicate the simple checks.

        // Server Health
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $diskUsage = $diskTotal > 0 ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1) . '%' : 'Unknown';

        // Backup Status
        $lastBackup = Backup::where('user_id', $userId)->where('is_successful', true)->latest()->first();
        $backupInfo = $lastBackup
            ? "Safe. Last run: " . $lastBackup->created_at->diffForHumans()
            : "Warning: No recent backups found.";

        // Security / 2FA Status
        $user = $request->user();
        $securityInfo = [
            '2fa_enabled' => $user->two_factor_enabled ? 'Yes' : 'No',
            'email' => $user->email,
            'role' => 'Admin'
        ];

        // 2. Full Context Injection
        $projects = PortfolioProject::all(['name', 'description', 'tech_stack'])->toArray();
        $skills = PortfolioSkill::all(['name', 'level'])->toArray();
        $experiences = PortfolioExperience::all(['company', 'title', 'start_date', 'end_date', 'description'])->toArray();
        $profile = PortfolioProfile::first(['headline', 'summary', 'about_me', 'location', 'email_public', 'website_url', 'github_url', 'linkedin_url']);

        $contextData = [
            'system_status' => [
                'disk_usage' => $diskUsage,
                'backup_status' => $backupInfo,
                'security' => $securityInfo,
            ],
            'profile' => $profile ? $profile->toArray() : null,
            'projects' => $projects,
            'skills' => $skills,
            'experience' => $experiences,
            'navigation_map' => [
                'MFA/2FA Setup' => '/security',
                'Backups' => '/security/backup',
                'Dashboard' => '/',
                'Portfolio Profile' => '/portfolio/profile',
                'Projects' => '/portfolio/projects',
            ]
        ];

        // 3. Prepare System Instruction
        $systemPrompt = "You are the Johnrak AI Admin Assistant.\n\n" .
            "**CORE OBJECTIVE:**\n" .
            "Assist the administrator (Vorak) with managing his system, checking health status, and navigating the dashboard.\n\n" .
            "**REAL-TIME SYSTEM DATA:**\n" .
            "Use this live data to answer questions:\n" . json_encode($contextData['system_status']) . "\n\n" .
            "**NAVIGATION MAP:**\n" .
            "If the user asks 'Where can I...' or 'How do I...', refer to these paths:\n" . json_encode($contextData['navigation_map']) . "\n\n" .
            "**PORTFOLIO CONTEXT:**\n" .
            "You also have access to the portfolio data if needed: " . json_encode(['projects' => $projects]) . "\n\n" .
            "**BEHAVIOR:**\n" .
            "1. **System Monitor:** If asked about health/backups, report the exact status from the data above.\n" .
            "2. **Navigator:** If the user explicitly asks to go somewhere (e.g., 'Take me to dashboard', 'Go to settings'), output a special command at the end of your response: `[NAVIGATE:/path]`. Example: 'Sure! [NAVIGATE:/security]'.\n" .
            "3. **Assistant:** Be helpful, concise, and professional.\n";

        // 4. Prepare History
        $history = is_array($conv->messages) ? $conv->messages : [];
        $geminiHistory = [];
        foreach (array_slice($history, max(0, count($history) - 6)) as $msg) {
            $role = ($msg['role'] === 'user') ? Role::USER : Role::MODEL;
            if (!empty($msg['content'])) {
                $geminiHistory[] = Content::parse(part: $msg['content'], role: $role);
            }
        }

        // 5. Call Gemini
        $answer = '';
        try {
            $response = Gemini::generativeModel('gemini-flash-latest')
                ->withSystemInstruction(Content::parse(part: $systemPrompt))
                ->startChat(history: $geminiHistory)
                ->sendMessage($message);

            $answer = $response->text();
        } catch (\Throwable $e) {
            Log::error("Gemini chat error: " . $e->getMessage());
            $answer = "System error: " . $e->getMessage();
        }

        // 6. Save Conversation
        $newHistory = array_merge($history, [
            ['role' => 'user', 'content' => $message],
            ['role' => 'assistant', 'content' => $answer],
        ]);
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

    public function generateCaseStudy(Request $request)
    {
        $this->configureGemini();
        $request->validate([
            'notes' => 'nullable|string',
            'repo_url' => 'nullable|url',
            'type' => 'nullable|string|in:project,education,experience',
        ]);

        $notes = $request->input('notes');
        $repoUrl = $request->input('repo_url');
        $type = $request->input('type', 'project');

        if (!$notes && !$repoUrl) {
            return response()->json(['error' => 'Please provide manual notes or a GitHub URL.'], 422);
        }

        $context = "";

        // 1. Fetch README from GitHub if URL provided
        if ($repoUrl) {
            try {
                // Parse "https://github.com/user/repo" -> "user/repo"
                $path = parse_url($repoUrl, PHP_URL_PATH);
                $path = trim($path, '/');

                // Try main then master
                $readme = @file_get_contents("https://raw.githubusercontent.com/{$path}/main/README.md");
                if (!$readme) {
                    $readme = @file_get_contents("https://raw.githubusercontent.com/{$path}/master/README.md");
                }

                if ($readme) {
                    $context .= "GitHub README Content:\n" . substr($readme, 0, 5000) . "\n\n"; // Limit size
                }
            } catch (\Exception $e) {
                // Ignore fetch errors, just use notes
            }
        }

        // 2. Append Manual Notes
        if ($notes) {
            $context .= "Manual Notes:\n" . $notes . "\n";
        }

        // 3. Construct Prompt based on Type
        if ($type === 'education') {
            $prompt = "You are a Professional Academic Copywriter. Take the following education notes:\n\n" .
                "[" . $context . "]\n\n" .
                "Generate a professional description for a CV/Portfolio focusing on:\n" .
                "- Key coursework and technical skills learned.\n" .
                "- Thesis or capstone projects (if mentioned).\n" .
                "- Academic achievements, GPA, or honors.\n\n" .
                "Format: Markdown bullet points. Tone: Scholarly yet practical.";
        } elseif ($type === 'experience') {
            $prompt = "You are a Senior Career Coach. Take the following job experience notes:\n\n" .
                "[" . $context . "]\n\n" .
                "Generate a job description in Markdown format using the STAR method:\n" .
                "- Situation/Task: Context of the role.\n" .
                "- Action: Key responsibilities and technologies used.\n" .
                "- Result: Quantifiable achievements.\n\n" .
                "Tone: Executive and results-driven.";
        } else {
            // Project (Default)
            $prompt = "You are a Professional Technical Copywriter. Take the following project notes and/or README content:\n\n" .
                "[" . $context . "]\n\n" .
                "Generate a case study in Markdown format using the STAR method:\n" .
                "- Situation: The high-level context.\n" .
                "- Task: The specific challenge or bug.\n" .
                "- Action: The technical steps and tools used.\n" .
                "- Result: The quantifiable outcome (e.g., 50% faster, zero bugs).\n\n" .
                "Tone: Results-oriented and senior-level.";
        }

        // 4. Call Gemini
        try {
            $response = Gemini::generativeModel('gemini-flash-latest')->generateContent($prompt);
            return response()->json(['markdown' => $response->text()]);
        } catch (\Exception $e) {
            Log::error("Case Study Generation Error: " . $e->getMessage());
            return response()->json(['error' => 'AI generation failed: ' . $e->getMessage()], 500);
        }
    }

    public function generateBio(Request $request)
    {
        $this->configureGemini();

        // 1. Gather Full Context
        $projects = PortfolioProject::all(['name', 'description', 'tech_stack'])->toArray();
        $skills = PortfolioSkill::all(['name', 'level'])->toArray();
        $experiences = PortfolioExperience::all(['company', 'title', 'start_date', 'end_date', 'description'])->toArray();
        $educations = \App\Models\PortfolioEducation::all(['school', 'degree', 'field_of_study', 'start_date', 'end_date', 'description'])->toArray();
        $profile = PortfolioProfile::first();

        $contextData = [
            'existing_summary' => $profile->summary ?? '',
            'existing_about_me' => $profile->about_me ?? '',
            'projects' => $projects,
            'skills' => $skills,
            'experience' => $experiences,
            'education' => $educations,
        ];

        // 2. Construct Prompt
        $prompt = "You are a Personal Branding Expert. I need you to write a professional 'Summary' and 'About Me' for my portfolio based on my actual data below.\n\n" .
            "DATA SOURCE:\n" . json_encode($contextData) . "\n\n" .
            "INSTRUCTIONS:\n" .
            "1. Analyze my skills, projects, and experience to find common themes (e.g., 'Full Stack Expert', 'System Architect').\n" .
            "2. Write a 'Summary' (max 200 chars): A punchy headline bio for the hero section.\n" .
            "3. Write an 'About Me' (max 300 words): A compelling narrative connecting my background, key achievements, and technical philosophy.\n" .
            "4. Return ONLY valid JSON in this format: { \"summary\": \"...\", \"about_me\": \"...\" }";

        // 3. Call Gemini
        try {
            $response = Gemini::generativeModel('gemini-flash-latest')->generateContent($prompt);
            $text = $response->text();

            // Cleanup JSON: Extract the first JSON object found
            if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
                $text = $matches[0];
            }

            $data = json_decode($text, true);

            if (!$data) {
                return response()->json(['error' => 'AI failed to generate valid JSON.'], 500);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error("Bio Generation Error: " . $e->getMessage());
            return response()->json(['error' => 'AI generation failed: ' . $e->getMessage()], 500);
        }
    }
}
