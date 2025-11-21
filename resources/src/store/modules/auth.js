import Vue from 'vue';
import Vuex from 'vuex';
import axios from 'axios';

Vue.use(Vuex);

const state = {
    isAuthenticated: false,
    Permissions: null,
    user: {},
    loading: false,
    error: null,
    notifs: 0,
    Default_Language: 'en',
    show_language: 1,
    availableLanguages: [],
};

const getters = {
    isAuthenticated: state => state.isAuthenticated,
    currentUser: state => state.user,
    currentUserPermissions: state => state.Permissions,
    loading: state => state.loading,
    notifs_alert: state => state.notifs,
    DefaultLanguage: state => state.Default_Language,
    show_language: state => state.show_language,
    error: state => state.error,
    getAvailableLanguages: state => state.availableLanguages,

};

const mutations = {
    setLoading(state, data) {
        state.loading = data;
        state.error = null;
    },
    setError(state, data) {
        state.error = data;
        state.loading = false;
    },
    clearError(state) {
        state.error = null;
    },
    setPermissions(state, Permissions) {
        state.Permissions = Permissions;
    },

    setUser(state, user) {
        state.user = user;
    },

    SetDefaultLanguage(state, { i18n, Language }) {
        if (i18n) {
            i18n.locale = Language;
        }
        state.Default_Language = Language;
    },

    Notifs_alert(state, notifs) {
        state.notifs = notifs;
    },
    show_language(state, show_language) {
        state.show_language = show_language;
    },
    setAvailableLanguages(state, languages) {
        state.availableLanguages = languages;
    },
    logout(state) {
        state.user = null;
        state.Permissions = null;
        state.loading = false;
        state.error = null;
    },
};

const actions = {
    async refreshUserPermissions({ commit }, i18n) {
        try {
            const userAuth = await axios.get("get_user_auth");
            const { permissions, user, notifs } = userAuth.data;

            commit('setPermissions', permissions);
            commit('setUser', user);
            commit('Notifs_alert', notifs);
            commit('show_language', user.show_language);
            commit('SetDefaultLanguage', { i18n, Language: user.default_language || 'en' });

        } catch (error) {
            commit('setPermissions', null);
            commit('setUser', null);
            commit('Notifs_alert', null);
            commit('show_language', null);
            commit('SetDefaultLanguage', { i18n, Language: 'en' });
        }
    },

     async loadAvailableLanguages({ commit }) {
        try {
            const response = await axios.get("/languages"); // must return: [{ name, locale, flag }]
            commit('setAvailableLanguages', response.data);
        } catch (error) {
            console.warn("⚠️ Failed to load languages:", error);
        }
    },

   logout({ commit }) {
    axios.post('/logout', {}, { baseURL: '' }) // override the baseURL here
        .then(() => {
            window.location.href = '/login';
        });
    }
};

export default {
    state,
    getters,
    actions,
    mutations
};
