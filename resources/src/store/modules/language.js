import Vue from 'vue';
import VueLocalStorage from 'vue-localstorage';

Vue.use(VueLocalStorage);

export default {
  namespaced: true,

  state: {
    language: Vue.localStorage.get('language') || 'en',
  },

  mutations: {
    SET_LANGUAGE(state, lang) {
      Vue.localStorage.set('language', lang);
      state.language = lang;
    },
  },

  actions: {
    async setLanguage({ commit }, payload) {
      let selected = 'en';

      if (typeof payload === 'string') {
        selected = payload;
      } else if (Array.isArray(payload)) {
        selected = payload
          .map(l => l.substring(0, 2))
          .find(code => !!code) || 'en';
      }

      // Update localStorage & state
      commit('SET_LANGUAGE', selected);

      // ✅ Also update backend
       try {
         await axios.post(`/languages_setting/set-default/${selected}`);
          } catch (error) {
            console.warn('Failed to sync default language to backend:', error);
          }
    },
  }

};
