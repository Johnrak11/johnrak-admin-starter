<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Trade;

class CheckTradesCommand extends Command
{
    protected $signature = 'trades:check';
    protected $description = 'Check active trades against live prices and send Telegram alerts';

    public function handle(): int
    {
        $symbols = Trade::where('status', 'active')
            ->select('symbol')
            ->distinct()
            ->pluck('symbol')
            ->map(fn ($s) => strtoupper($s))
            ->values()
            ->all();

        if (empty($symbols)) {
            $this->info('No active trades.');
            return self::SUCCESS;
        }

        $prices = [];
        foreach ($symbols as $symbol) {
            $prices[$symbol] = $this->getPrice($symbol);
        }

        Trade::where('status', 'active')
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($prices) {
                foreach ($chunk as $trade) {
                    $symbol = strtoupper($trade->symbol);
                    $price = $prices[$symbol] ?? null;
                    if (!$price) {
                        continue;
                    }

                    $prevStatus = $trade->status;
                    if ($trade->tp2 && $price >= (float)$trade->tp2) {
                        $trade->status = 'tp2';
                    } elseif ($trade->tp1 && $price >= (float)$trade->tp1) {
                        $trade->status = 'tp1';
                    } elseif ($trade->sl && $price <= (float)$trade->sl) {
                        $trade->status = 'sl';
                    }

                    if ($trade->status !== $prevStatus && $trade->status !== 'active') {
                        $trade->triggered_at = now();
                        $trade->save();
                        \App\Services\TelegramService::sendMessage(
                            "📣 Trade Alert {$trade->symbol}\nStatus: {$trade->status}\nPrice: $" . number_format($price, 4)
                        );
                    } else {
                        $trade->save();
                    }
                }
            });

        $this->info('Trades checked.');
        return self::SUCCESS;
    }

    private function getPrice(string $symbol): ?float
    {
        try {
            $res = Http::timeout(3)->get('https://api.binance.com/api/v3/ticker/24hr', [
                'symbol' => "{$symbol}USDT",
            ]);
            if ($res->successful()) {
                $data = $res->json();
                return isset($data['lastPrice']) ? (float)$data['lastPrice'] : null;
            }
        } catch (\Throwable $e) {
            Log::warning("Binance price failed for {$symbol}: " . $e->getMessage());
        }

        $map = [
            'BTC' => 'bitcoin',
            'ETH' => 'ethereum',
            'SOL' => 'solana',
            'XRP' => 'ripple',
            'DOGE' => 'dogecoin',
            'BNB' => 'binancecoin',
            'ADA' => 'cardano',
        ];
        $cgId = $map[$symbol] ?? strtolower($symbol);
        try {
            $cg = Http::timeout(4)->get('https://api.coingecko.com/api/v3/coins/markets', [
                'vs_currency' => 'usd',
                'ids' => $cgId,
            ]);
            if ($cg->successful() && !empty($cg->json())) {
                $data = $cg->json()[0] ?? null;
                return $data ? (float)$data['current_price'] : null;
            }
        } catch (\Throwable $e) {
            Log::warning("CoinGecko price failed for {$symbol}: " . $e->getMessage());
        }
        return null;
    }
}

