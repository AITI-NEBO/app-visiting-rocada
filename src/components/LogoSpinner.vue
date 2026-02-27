<template>
  <div class="logo-spinner" :class="[size]">
    <div class="spinner-ring"></div>
    <img src="/logo.jpeg" alt="" class="spinner-logo" />
  </div>
</template>

<script setup>
defineProps({
  size: {
    type: String,
    default: 'medium',
    validator: v => ['small', 'medium', 'large'].includes(v)
  }
})
</script>

<style scoped>
.logo-spinner {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.logo-spinner.small {
  width: 32px;
  height: 32px;
}

.logo-spinner.medium {
  width: 56px;
  height: 56px;
}

.logo-spinner.large {
  width: 80px;
  height: 80px;
}

.spinner-ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 2px solid transparent;
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary)) border-box;
  -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor;
  mask-composite: exclude;
  animation: spin 1.2s linear infinite;
}

.spinner-logo {
  border-radius: 50%;
  object-fit: cover;
  animation: pulse 2s ease-in-out infinite;
}

.small .spinner-logo {
  width: 22px;
  height: 22px;
}

.medium .spinner-logo {
  width: 40px;
  height: 40px;
}

.large .spinner-logo {
  width: 60px;
  height: 60px;
}
</style>
