<template>
  <div class="rounded-lg border border-border bg-card text-card-foreground shadow-sm">
    <div v-if="$slots.header" class="border-b border-border p-4">
      <slot name="header" />
    </div>
    <div class="p-4">
      <slot />
    </div>
  </div>
</template>
