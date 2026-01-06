<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="flex items-center justify-between">
          <div>
            <div class="text-lg font-semibold">Transactions</div>
            <div class="text-sm text-muted-foreground">
              View all payment transactions
            </div>
          </div>
          <div class="flex gap-2">
            <Input
              v-model="search"
              placeholder="Search by Order ID..."
              class="w-48"
              @input="load"
            />
            <select
              v-model="statusFilter"
              @change="load"
              class="rounded-lg border border-input bg-background px-3 py-2 text-sm"
            >
              <option value="">All Status</option>
              <option value="pending">Pending</option>
              <option value="paid">Paid</option>
              <option value="failed">Failed</option>
              <option value="expired">Expired</option>
              <option value="error">Error</option>
            </select>
          </div>
        </div>
      </template>

      <div v-if="loading" class="text-sm text-muted-foreground">
        Loading...
      </div>

      <div v-else-if="transactions.length === 0" class="text-center py-12">
        <div class="text-4xl mb-4">📭</div>
        <p class="text-sm text-muted-foreground">No transactions found</p>
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="tx in transactions"
          :key="tx.id"
          class="rounded-lg border border-border bg-muted/30 p-4"
        >
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-3 mb-2">
                <div class="text-sm font-medium">Order: {{ tx.order_id }}</div>
                <span
                  :class="{
                    'bg-green-500/20 text-green-600': tx.status === 'paid',
                    'bg-yellow-500/20 text-yellow-600': tx.status === 'pending',
                    'bg-red-500/20 text-red-600':
                      tx.status === 'failed' || tx.status === 'error',
                    'bg-gray-500/20 text-gray-600': tx.status === 'expired',
                  }"
                  class="px-2 py-1 rounded text-xs font-medium"
                >
                  {{ tx.status.toUpperCase() }}
                </span>
              </div>
              <div class="text-sm text-muted-foreground space-y-1">
                <div>Amount: ${{ tx.amount }} {{ tx.currency }}</div>
                <div v-if="tx.payer_name">
                  Payer: {{ tx.payer_name }}
                  <span v-if="tx.payer_phone"> ({{ tx.payer_phone }})</span>
                </div>
                <div v-if="tx.transaction_id">
                  TX ID: <span class="font-mono text-xs">{{ tx.transaction_id }}</span>
                </div>
                <div>
                  Created: {{ formatDate(tx.created_at) }}
                </div>
                <div v-if="tx.paid_at">
                  Paid: {{ formatDate(tx.paid_at) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div
          v-if="pagination"
          class="flex items-center justify-between pt-4 border-t border-border"
        >
          <div class="text-sm text-muted-foreground">
            Showing {{ pagination.from }} to {{ pagination.to }} of
            {{ pagination.total }}
          </div>
          <div class="flex gap-2">
            <Button
              variant="ghost"
              size="sm"
              @click="load(pagination.current_page - 1)"
              :disabled="!pagination.prev_page_url"
            >
              Previous
            </Button>
            <Button
              variant="ghost"
              size="sm"
              @click="load(pagination.current_page + 1)"
              :disabled="!pagination.next_page_url"
            >
              Next
            </Button>
          </div>
        </div>
      </div>
    </Card>
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { api } from "../../lib/api";
import Card from "../../components/ui/Card.vue";
import Input from "../../components/ui/Input.vue";
import Button from "../../components/ui/Button.vue";
import { formatDistanceToNow } from "date-fns";

const loading = ref(true);
const transactions = ref([]);
const pagination = ref(null);
const search = ref("");
const statusFilter = ref("");

function formatDate(dateStr) {
  try {
    return formatDistanceToNow(new Date(dateStr), { addSuffix: true });
  } catch (e) {
    return dateStr;
  }
}

async function load(page = 1) {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      page: page.toString(),
    });
    if (search.value) params.append("search", search.value);
    if (statusFilter.value) params.append("status", statusFilter.value);

    const res = await api().get(`/api/payment/transactions?${params}`);
    transactions.value = res.data.data || [];
    pagination.value = {
      current_page: res.data.current_page,
      from: res.data.from,
      to: res.data.to,
      total: res.data.total,
      prev_page_url: res.data.prev_page_url,
      next_page_url: res.data.next_page_url,
    };
  } finally {
    loading.value = false;
  }
}

onMounted(() => load());
</script>
