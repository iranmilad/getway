/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */
 window.$ = window.jQuery = require('jquery'); // <-- main, not 'slim'

 require('bootstrap');




 window.Vue = require("vue").default;

 window.axios = require('axios');


 window.axios.defaults.baseURL = document.head.querySelector('meta[name="api-base-url"]').content;


 Vue.component('signal-table-vue', require('./components/TableComponent.vue').default);
 //Vue.component('example-component', require('./components/ExampleComponent.vue').default);

 /**
  * Next, we will create a fresh Vue application instance and attach it to
  * the page. Then, you may begin adding components to this application
  * or customize the JavaScript scaffolding to fit your unique needs.
  */

 const app = new Vue({
     el: '#app',

 });

