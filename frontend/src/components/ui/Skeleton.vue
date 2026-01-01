<template>
  <div class="animate-pulse rounded-md bg-muted" :class="className"></div>
</template>

<script setup>
defineProps({
  className: { type: String, default: '' }
})
</script>
