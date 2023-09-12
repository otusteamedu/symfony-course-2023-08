import './bootstrap.js';
import './styles/app.scss';
import {createApp} from 'vue';
import App from './components/App';
require('bootstrap');

const RootComponent = {
    components: {
        App,
    },
};

const app =  createApp(RootComponent).mount('#app');