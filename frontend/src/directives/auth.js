import Vue from 'vue'
import store from '@/store'

Vue.directive('auth', {
  inserted(el, binding) {
    const check = () => {
      const perm = binding.value
      if (perm && !store.getters.hasPerm(perm)) {
        el.style.display = 'none'
        el.classList.add('auth-hidden')
      } else {
        el.style.display = ''
        el.classList.remove('auth-hidden')
      }
    }
    check()
    el.__unwatchAuth = store.watch(
      state => state.permissions,
      () => check()
    )
  },
  unbind(el) {
    if (el.__unwatchAuth) {
      el.__unwatchAuth()
      delete el.__unwatchAuth
    }
  }
})
