<template>
  <button
    :type="type"
    :disabled="disabled"
    class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition
           disabled:opacity-50 disabled:cursor-not-allowed"
    :class="classes"
  >
    <slot />
  </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  type: { type: String, default: 'button' },
  disabled: { type: Boolean, default: false },
  variant: { type: String, default: 'default' } // default | ghost | danger
})

const classes = computed(() => {
  if (props.variant === 'ghost') return 'border border-input bg-background hover:bg-accent hover:text-accent-foreground'
  if (props.variant === 'danger') return 'bg-destructive text-destructive-foreground hover:bg-destructive/90'
  return 'bg-primary text-primary-foreground hover:bg-primary/90'
})
</script>
