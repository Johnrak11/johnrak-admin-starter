<template>
  <div class="relative w-full overflow-hidden rounded-full bg-secondary h-4">
    <div
      class="h-full w-full flex-1 bg-primary transition-all"
      :style="{ transform: `translateX(-${100 - (value || 0)}%)` }"
    ></div>
  </div>
</template>

<script setup>
defineProps({
  value: { type: Number, default: 0 }
})
</script>
