import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import { router } from './router';
import vuetify from './plugins/vuetify';
import '@/scss/style.scss';
import PerfectScrollbar from 'vue3-perfect-scrollbar';
import VueApexCharts from 'vue3-apexcharts';
import VueTablerIcons from 'vue-tabler-icons';
import HighchartsVue from 'highcharts-vue'
const app = createApp(App);

app.use(PerfectScrollbar);
app.use(VueTablerIcons);
app.use(router); 
app.use(createPinia());
app.use(VueApexCharts);
app.use(HighchartsVue)
app.use(vuetify).mount('#app');
