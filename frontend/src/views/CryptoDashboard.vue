<template>
  <div
    class="space-y-4 h-full flex flex-col p-2 sm:p-3 overflow-y-auto overflow-x-hidden custom-scrollbar"
  >
    <!-- Top Bar -->
    <div
      class="flex flex-col md:flex-row md:items-center justify-between gap-4 shrink-0 bg-card border border-border p-3 rounded-lg shadow-sm sticky top-0 z-30 backdrop-blur supports-backdrop:backdrop-blur"
    >
      <div class="flex items-center gap-3">
        <div class="bg-primary/10 p-1.5 rounded-md">
          <span class="text-lg">🦁</span>
        </div>
        <div>
          <h1 class="text-lg font-semibold tracking-tight">TradeMind AI</h1>
          <p class="text-[10px] text-muted-foreground">Crypto Intelligence</p>
        </div>
      </div>

      <div class="flex items-center gap-2 w-full md:w-auto">
        <div class="relative flex-1 md:flex-none">
          <select
            v-model="selectedCoin"
            @change="fetchData"
            class="h-8 pl-3 pr-8 rounded-md border border-input bg-background text-xs focus:ring-1 focus:ring-primary appearance-none cursor-pointer hover:bg-accent transition-colors w-full md:w-[160px]"
          >
            <option value="BTC">Bitcoin (BTC)</option>
            <option value="ETH">Ethereum (ETH)</option>
            <option value="SOL">Solana (SOL)</option>
            <option value="XRP">Ripple (XRP)</option>
            <option value="DOGE">Dogecoin (DOGE)</option>
            <option value="BNB">Binance Coin (BNB)</option>
          </select>
          <div
            class="absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none text-muted-foreground text-[10px]"
          >
            ▼
          </div>
        </div>

        <Button
          @click="fetchData"
          :disabled="loading"
          variant="ghost"
          size="sm"
          class="h-8 w-8 p-0 rounded-md hover:bg-accent shrink-0"
        >
          <span v-if="loading" class="animate-spin text-sm">↻</span>
          <span v-else class="text-sm">↻</span>
        </Button>
      </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 flex-1">
      <!-- Left Column: Chart (8 cols) -->
      <div class="lg:col-span-8 flex flex-col gap-4">
        <!-- TradingView Chart -->
        <div
          class="bg-card border border-border rounded-lg overflow-hidden flex flex-col shadow-sm relative group h-[360px] sm:h-[420px] md:h-[520px] lg:h-[600px]"
        >
          <div
            class="flex-1 bg-background relative z-10"
            :id="'tv_chart_container_' + chartKey"
            :key="chartKey"
          >
            <!-- Widget mounts here -->
          </div>
          <div
            v-if="!chartLoaded"
            class="absolute inset-0 flex flex-col items-center justify-center text-muted-foreground z-20 pointer-events-none bg-background/50"
          >
            <div class="animate-pulse text-2xl mb-2">📊</div>
            <p class="text-xs">Loading Chart...</p>
          </div>
        </div>

        <!-- Manual Trade Setup Form -->
        <div
          class="shrink-0 bg-card border border-border rounded-lg p-3 shadow-sm"
        >
          <div class="flex items-center gap-2 mb-2">
            <span class="text-green-500 text-sm">⚡</span>
            <h3 class="font-medium text-xs">Quick Trade Setup</h3>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-2">
            <div class="space-y-1">
              <label
                class="text-[9px] uppercase tracking-wider text-muted-foreground font-medium"
                >Entry</label
              >
              <input
                type="number"
                class="w-full bg-background border border-input rounded px-2 py-1 text-xs font-mono focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all"
                placeholder="0.00"
              />
            </div>
            <div class="space-y-1">
              <label
                class="text-[9px] uppercase tracking-wider text-green-500 font-medium"
                >TP 1</label
              >
              <input
                type="number"
                class="w-full bg-green-500/5 border border-green-500/20 rounded px-2 py-1 text-xs font-mono text-green-500 focus:border-green-500/50 focus:ring-1 focus:ring-green-500/50 outline-none transition-all"
                placeholder="0.00"
              />
            </div>
            <div class="space-y-1">
              <label
                class="text-[9px] uppercase tracking-wider text-green-500 font-medium"
                >TP 2</label
              >
              <input
                type="number"
                class="w-full bg-green-500/5 border border-green-500/20 rounded px-2 py-1 text-xs font-mono text-green-500 focus:border-green-500/50 focus:ring-1 focus:ring-green-500/50 outline-none transition-all"
                placeholder="0.00"
              />
            </div>
            <div class="space-y-1">
              <label
                class="text-[9px] uppercase tracking-wider text-red-500 font-medium"
                >Stop Loss</label
              >
              <input
                type="number"
                class="w-full bg-red-500/5 border border-red-500/20 rounded px-2 py-1 text-xs font-mono text-red-500 focus:border-red-500/50 focus:ring-1 focus:ring-red-500/50 outline-none transition-all"
                placeholder="0.00"
              />
            </div>
            <div class="flex items-end col-span-2 md:col-span-1">
              <button
                class="w-full h-[26px] bg-primary hover:bg-primary/90 text-primary-foreground text-[10px] font-medium rounded transition-all flex items-center justify-center gap-1.5"
              >
                <span>💾</span> Save
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column: AI & News (4 cols) -->
      <div class="lg:col-span-4 flex flex-col gap-4">
        <!-- AI Analyst -->
        <div
          class="bg-card border border-border rounded-lg shadow-sm flex flex-col shrink-0"
        >
          <div
            class="p-3 border-b border-border flex items-center justify-between"
          >
            <div class="flex items-center gap-2">
              <span class="text-base">🔮</span>
              <h3 class="font-medium text-xs">AI Analyst</h3>
            </div>
            <!-- Removed 'disabled' check on market to allow analysis even if partial data fails -->
            <button
              @click.stop="analyze"
              :disabled="analyzing"
              class="bg-purple-600 hover:bg-purple-500 text-white text-[10px] px-2.5 py-1 rounded font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5 cursor-pointer relative z-20"
            >
              <span v-if="analyzing" class="animate-spin">✨</span>
              {{ analyzing ? "Thinking..." : "Analyze" }}
            </button>
          </div>

          <div
            class="p-3 min-h-[180px] max-h-[350px] overflow-y-auto custom-scrollbar relative z-10"
          >
            <div
              v-if="analysis"
              class="prose prose-invert prose-sm max-w-none text-xs leading-relaxed"
              v-html="renderMarkdown(analysis)"
            ></div>

            <div
              v-else-if="analyzing"
              class="flex flex-col items-center justify-center py-8 text-center text-muted-foreground"
            >
              <div class="text-2xl mb-2 animate-pulse">🧠</div>
              <p class="text-xs font-medium">Processing...</p>
            </div>

            <div
              v-else
              class="flex flex-col items-center justify-center py-8 text-center text-muted-foreground border border-dashed border-border rounded m-1"
            >
              <div class="text-2xl mb-2 opacity-50">📈</div>
              <p class="text-xs font-medium">Ready to Analyze</p>
              <p class="text-[10px] mt-0.5 opacity-60 max-w-[150px]">
                Get predictions for {{ selectedCoin }}
              </p>
            </div>
          </div>
        </div>

        <!-- News Feed -->
        <div
          class="flex-1 bg-card border border-border rounded-lg flex flex-col shadow-sm min-h-[200px]"
        >
          <div class="p-3 border-b border-border flex items-center gap-2">
            <span class="text-base">📰</span>
            <h3 class="font-medium text-xs">Market News</h3>
          </div>

          <div class="flex-1 overflow-y-auto p-2 custom-scrollbar">
            <div v-if="news && news.length > 0" class="space-y-1.5">
              <div
                v-for="item in news"
                :key="item.id"
                class="group p-2 rounded bg-muted/30 hover:bg-muted/60 transition-colors cursor-pointer"
              >
                <a :href="item.url" target="_blank" class="block">
                  <h4
                    class="text-[11px] font-medium leading-snug group-hover:text-primary transition-colors mb-1 line-clamp-2"
                  >
                    {{ item.title }}
                  </h4>
                  <div
                    class="flex items-center justify-between text-[9px] text-muted-foreground"
                  >
                    <div class="flex items-center gap-1">
                      <span>{{ item.domain }}</span>
                      <span>•</span>
                      <span>{{ formatDate(item.published_at) }}</span>
                    </div>
                  </div>
                </a>
              </div>
            </div>

            <div
              v-else-if="!loading"
              class="h-full flex flex-col items-center justify-center text-muted-foreground opacity-50 p-4 text-center"
            >
              <span class="text-xl mb-1">📭</span>
              <span class="text-[10px]">No news available</span>
              <span class="text-[9px] mt-1 text-red-400/50" v-if="newsError"
                >API Error: {{ newsError }}</span
              >
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch, nextTick } from "vue";
import Card from "../components/ui/Card.vue";
import Button from "../components/ui/Button.vue";
import { api } from "../lib/api";
import MarkdownIt from "markdown-it";
import { formatDistanceToNow } from "date-fns";

const md = new MarkdownIt();

const selectedCoin = ref("BTC");
const loading = ref(false);
const analyzing = ref(false);
const market = ref(null);
const news = ref([]);
const newsError = ref(null);
const analysis = ref(null);
const chartLoaded = ref(false);
const chartKey = ref(0);

const isPositive = computed(() => {
  return market.value && parseFloat(market.value.priceChangePercent) >= 0;
});

function formatVolume(vol) {
  const v = parseFloat(vol);
  if (v >= 1e9) return (v / 1e9).toFixed(2) + "B";
  if (v >= 1e6) return (v / 1e6).toFixed(2) + "M";
  if (v >= 1e3) return (v / 1e3).toFixed(2) + "K";
  return v.toFixed(2);
}

function formatDate(dateStr) {
  try {
    return formatDistanceToNow(new Date(dateStr), { addSuffix: true });
  } catch (e) {
    return dateStr;
  }
}

function renderMarkdown(text) {
  return md.render(text);
}

// Load TradingView Widget
function loadTradingViewWidget() {
  chartLoaded.value = false;
  chartKey.value++; // Force re-render of container

  nextTick(() => {
    const containerId = "tv_chart_container_" + chartKey.value;
    if (!document.getElementById(containerId)) return;

    const script = document.createElement("script");
    script.src = "https://s3.tradingview.com/tv.js";
    script.async = true;
    script.onload = () => {
      if (
        typeof TradingView !== "undefined" &&
        document.getElementById(containerId)
      ) {
        new TradingView.widget({
          autosize: true,
          symbol: "BINANCE:" + selectedCoin.value + "USDT",
          interval: "D",
          timezone: "Etc/UTC",
          theme: "dark",
          style: "1",
          locale: "en",
          toolbar_bg: "#f1f3f6",
          enable_publishing: false,
          allow_symbol_change: true,
          container_id: containerId,
          hide_side_toolbar: false,
        });
        chartLoaded.value = true;
      }
    };
    document.head.appendChild(script);
  });
}

watch(selectedCoin, () => {
  loadTradingViewWidget();
});

async function fetchData() {
  loading.value = true;
  analysis.value = null;
  newsError.value = null;
  try {
    const res = await api().get(`/api/crypto/${selectedCoin.value}`);
    market.value = res.data.market;
    news.value = res.data.news || [];
    if (!news.value.length) newsError.value = "Check API Key";
  } catch (e) {
    console.error("Failed to fetch crypto data", e);
    market.value = null;
    news.value = [];
    newsError.value = "Network Error";
  } finally {
    loading.value = false;
  }
}

async function analyze() {
  if (!market.value) return;

  analyzing.value = true;

  const newsContext = news.value
    .map((n, i) => `${i + 1}. ${n.title}`)
    .join("\n");
  const prompt = `
ROLE: You are a Senior Crypto Quantitative Analyst (TradeMind AI).
TASK: Analyze the following data for ${
    selectedCoin.value
  } and provide a trading setup.

MARKET DATA:
- Price: $${market.value.lastPrice}
- 24h Change: ${market.value.priceChangePercent}%
- 24h High: $${market.value.highPrice}
- 24h Low: $${market.value.lowPrice}
- 24h Volume: $${formatVolume(market.value.quoteVolume)}

LATEST NEWS HEADLINES:
${newsContext}

OUTPUT FORMAT (Markdown):
## 🦁 TradeMind Analysis: ${selectedCoin.value}
**Sentiment Score:** (0-100, where 100 is Bullish)
**Trend Structure:** (e.g., "Bullish Consolidation" or "Bearish Rejection")

### 🎯 Trade Setup (NFA)
* **Entry Zone:** $...
* **Take Profit 1 (Conservative):** $...
* **Take Profit 2 (Aggressive):** $...
* **Stop Loss:** $...

**Reasoning:** (One short sentence explaining why).
`;

  try {
    const res = await api().post("/api/ai/chat", {
      message: prompt,
    });
    analysis.value = res.data.assistant;
  } catch (e) {
    analysis.value = "**Error:** AI Analysis failed. Please try again.";
  } finally {
    analyzing.value = false;
  }
}

onMounted(() => {
  fetchData();
  nextTick(() => {
    loadTradingViewWidget();
  });
});
</script>
