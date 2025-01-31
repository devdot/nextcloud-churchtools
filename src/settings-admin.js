import Vue from 'vue'
import App from './Components/SettingsAdmin.vue'
Vue.mixin({ methods: { t, n } })

const View = Vue.extend(App)
new View().$mount('#churchtools_integration_settings')
