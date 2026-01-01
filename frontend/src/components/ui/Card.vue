<template>
  <div
    class="rounded-lg border border-border bg-card text-card-foreground shadow-sm overflow-hidden min-w-0"
  >
    <div v-if="$slots.header" class="border-b border-border p-4 min-w-0">
      <slot name="header" />
    </div>
    <div class="p-4 min-w-0">
      <slot />
    </div>
  </div>
</template>
