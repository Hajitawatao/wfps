<template>
  <div class="panel empty">
    <b>Page not found</b>
    <span>The address <code>{{ $route.fullPath }}</code> is not part of the WFP system.</span>
    <div class="mt-4"><RouterLink to="/" class="btn primary">Go to the Summary</RouterLink></div>
  </div>
</template>
