<template>
  <button
    type="button"
    role="switch"
    :aria-checked="modelValue ? 'true' : 'false'"
    class="inline-flex h-6 w-11 items-center rounded-full border border-border bg-muted transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
    :class="modelValue ? 'bg-primary' : ''"
    @click="$emit('update:modelValue', !modelValue)"
  >
    <span
      class="h-5 w-5 rounded-full bg-background shadow-sm transition"
      :class="modelValue ? 'translate-x-5' : 'translate-x-1'"
    />
  </button>
</template>

<script setup>
defineProps({
  modelValue: { type: Boolean, default: false }
})

defineEmits(['update:modelValue'])
</script>

