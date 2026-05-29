import Vue from 'vue'
import Vuex from 'vuex'
import { getToken, setToken, removeToken } from '@/utils/auth'
import request from '@/api'

Vue.use(Vuex)

export default new Vuex.Store({
  state: {
    token: getToken(),
    admin: null,
    roles: [],
    menus: [],
    captchaKey: '',
    captchaImage: ''
  },
  mutations: {
    SET_TOKEN(state, token) {
      state.token = token
      setToken(token)
    },
    SET_ADMIN(state, admin) {
      state.admin = admin
    },
    SET_ROLES(state, roles) {
      state.roles = roles
    },
    SET_MENUS(state, menus) {
      state.menus = menus
    },
    SET_CAPTCHA(state, { key, image }) {
      state.captchaKey = key
      state.captchaImage = image
    },
    CLEAR(state) {
      state.token = ''
      state.admin = null
      state.roles = []
      state.menus = []
      removeToken()
    }
  },
  actions: {
    // 获取验证码
    async getCaptcha({ commit }) {
      const res = await request.get('/auth/captcha')
      if (res.code === 0) {
        commit('SET_CAPTCHA', { key: res.data.captcha_key, image: res.data.captcha_image })
      }
      return res
    },

    // 登录
    async login({ commit, dispatch, state }, { username, password, captcha_code }) {
      const res = await request.post('/auth/login', {
        username,
        password,
        captcha_key: state.captchaKey,
        captcha_code
      })
      if (res.code === 0) {
        commit('SET_TOKEN', res.data.token)
        await dispatch('getInfo')
      } else {
        dispatch('getCaptcha')
      }
      return res
    },

    // 获取管理员信息+菜单权限
    async getInfo({ commit }) {
      const res = await request.get('/auth/info')
      if (res.code === 0) {
        commit('SET_ADMIN', res.data.admin)
        commit('SET_ROLES', res.data.roles)
        commit('SET_MENUS', res.data.menus)
      }
      return res
    },

    // 登出
    async logout({ commit }) {
      await request.post('/auth/logout')
      commit('CLEAR')
    }
  }
})
