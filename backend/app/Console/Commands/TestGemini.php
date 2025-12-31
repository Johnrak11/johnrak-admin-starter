<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Gemini\Laravel\Facades\Gemini;

class TestGemini extends Command
{
    protected $signature = 'test:gemini';
    protected $description = 'Test Gemini API connection';

    public function handle()
    {
        $this->info('Testing Gemini API with gemini-flash-latest...');

        try {
            $response = Gemini::generativeModel('gemini-flash-latest')
                ->generateContent('Hello');
            $this->info('Response: ' . $response->text());
            $this->info('Success! gemini-flash-latest is working.');
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
