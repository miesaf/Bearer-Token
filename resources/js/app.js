/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

import './bootstrap';
import { createApp } from 'vue';

import ExampleComponent from './components/ExampleComponent.vue';
import PassportClients from './components/passport/Clients.vue';
import PassportAuthorizedClients from './components/passport/AuthorizedClients.vue';
import PassportPersonalAccessTokens from './components/passport/PersonalAccessTokens.vue';

/**
 * Next, we will create a fresh Vue application instance, register the
 * components so they can be used in the views, and attach it to the page.
 */

const app = createApp({});

app.component('example-component', ExampleComponent);
app.component('passport-clients', PassportClients);
app.component('passport-authorized-clients', PassportAuthorizedClients);
app.component('passport-personal-access-tokens', PassportPersonalAccessTokens);

app.mount('#app');
