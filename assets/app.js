import { registerVueControllerComponents } from '@symfony/ux-vue';
import './bootstrap.js';
import './styles/app.scss';
import Vue, {createApp} from 'vue';
import App from './components/App';
require('bootstrap');

createApp(App).mount('#app');

registerVueControllerComponents(require.context('./vue/controllers', true, /\.vue$/));