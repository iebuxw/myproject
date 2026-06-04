import store from '@/store'

Vue.directive('auth', {
  inserted(el, binding) {
    const perm = binding.value
    if (perm && !store.getters.hasPerm(perm)) {
      el.parentNode && el.parentNode.removeChild(el)
    }
  }
})
