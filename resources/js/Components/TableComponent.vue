<template>
    <div>

        <b-container fluid>
            <!-- User Interface controls -->
            <b-row>
                <b-col sm="2" md="3" class="my-1">
                        <b-form-group
                            label="Per page"
                            label-cols-sm="6"
                            label-cols-md="4"
                            label-cols-lg="3"
                            label-align-sm="right"
                            label-size="sm"
                            label-for="perPageSelect"
                            class="mb-1"
                        >
                        <b-form-select
                        v-model="perPage"
                        id="perPageSelect"
                        size="md"
                        class="mb-1"
                        :options="pageOptions"
                        ></b-form-select>
                    </b-form-group>
                </b-col>
            </b-row>
            <!-- Main table element -->
            <b-table
            show-empty
            striped
            responsive="true"
            stacked="md"
            table-class=""
            :items="items"
            :fields="fields"
            :current-page="currentPage"
            :per-page="perPage"
            :filter="filter"
            :filter-included-fields="filterOn"
            :sort-by.sync="sortBy"
            :sort-desc.sync="sortDesc"
            :sort-direction="sortDirection"
            @filtered="onFiltered"
            >

            <template v-slot:cell(actions)="row">
                <div class="d-none d-sm-block">
                    <b-button-group>
                    <a class="btn btn-dark" type="button" :href="'/machines/edit/'+row.item.id">
                    <b-icon icon="gear-fill"></b-icon> Setting
                    </a>
                    <b-button class="btn btn-warning" type="button" data-toggle="modal" data-target="#exampleModalLong"  size="md"  @click="View(row)">
                    <b-icon icon="eye-fill"></b-icon> View
                    </b-button>
                    </b-button-group>
                </div>
                <div class="d-block d-sm-none">
                    <b-button-group>
                    <a class="btn btn-dark" type="button" :href="'/machines/edit/'+row.item.id">
                    <b-icon icon="gear-fill"></b-icon>
                    </a>
                    <b-button class="btn btn-warning" type="button" data-toggle="modal" data-target="#exampleModalLong"  size="md" @click="View(row)">
                    <b-icon icon="eye-fill"></b-icon>
                    </b-button>
                    </b-button-group>
                </div>

            </template>

            <template v-slot:cell(active)="row">
                    <b-form-checkbox

                        v-show="access"
                        v-model="row.item.active"
                        name="active"
                        v-on:change="changeCalView(row)"
                        switch
                    >
                    Yes
                    </b-form-checkbox>
                    <div class="mb-0" v-show="!access">
                        <a  type="button" href="/subscription">
                        <b-icon-exclamation-triangle-fill></b-icon-exclamation-triangle-fill>
                        OFF
                        </a>
                    </div>
            </template>

            </b-table>
            <b-row>
                <b-col sm="6" md="4" class="my-1">
                    <b-pagination
                    v-model="currentPage"
                    :total-rows="totalRows"
                    :per-page="perPage"
                    align="fill"
                    size="md"
                    class="my-0"
                    first-text="First"
                    prev-text="Prev"
                    next-text="Next"
                    last-text="Last"
                    >


                    </b-pagination>
                </b-col>
            </b-row>

        </b-container>
    </div>

</template>


<script>

import {BootstrapVue, IconsPlugin } from 'bootstrap-vue';


// Install BootstrapVue
Vue.use(BootstrapVue)
// Optionally install the BootstrapVue icon components plugin
Vue.use(IconsPlugin)

  export default {
    components :{
    },
    props: ['dataUrl'],
    data() {
        return {
            show: false,
            variants: ['primary', 'secondary', 'success', 'warning', 'danger', 'info', 'light', 'dark'],
            headerBgVariant: 'dark',
            headerTextVariant: 'light',
            bodyBgVariant: 'light',
            bodyTextVariant: 'dark',
            footerBgVariant: 'warning',
            footerTextVariant: 'dark',
            items: [

            ],
            fields: [

            ],
            totalRows: 1,
            currentPage: 1,
            perPage: 10,
            pageOptions: [5, 10, 15],
            sortBy: '',
            sortDesc: false,
            sortDirection: 'asc',
            filter: null,
            filterOn: [],
            infoModal: {
            id: 'info-modal',
            title: '',
            content: ''
            },
            action_urls:[{
                update:'',
                delete:'',
                view:''
            }],
            messages:[],
            msgBox: '',
            access: true,
            selected:{
                item:{
                    market:'',
                    options:{type:'',value:'',method:'',activator:''},
                    symbols:[],
                    interval:'',

                },
            },
        }
    },
    computed: {
        sortOptions() {
        // Create an options list from our fields
        return this.fields
            .filter(f => f.sortable)
            .map(f => {
            return { text: f.label, value: f.key }
            })
        }
    },
    mounted() {
        // Set the initial number of items

        this.getList();

    },
    methods: {
        info(item, index, button) {
            this.infoModal.title = `Row index: ${index}`
            this.infoModal.content = JSON.stringify(item, null, 2)
            this.$root.$emit('bv::show::modal', this.infoModal.id, button)
        },
        async UpdateRecord(item){


            await axios.post(this.action_urls.update,item)
            .then(response=>{
                console.log(response);

                toastr.success(this.messages.update.success.text, this.messages.update.success.title)
                this.getList();
            })
            .catch(e => {
                toastr.error(this.messages.update.error.text, this.messages.update.error.title)
                //this.$bvModal.show('modal-scrollable');
            })

        },
        async DeleteRecord(item) {


            await axios.delete(this.action_urls.delete+`${item.item.id}`)
            .then(response => {
                this.getList();
                console.log(response);
                toastr.success(this.messages.delete.success.text, this.messages.delete.success.title)
                //$bvModal.show('modal-scrollable');
            })
            .catch(e => {
                //console.log(e);
                toastr.error(this.messages.delete.error.text, this.messages.delete.error.title)
                //this.$bvModal.show('modal-scrollable');
            })

        },
        View(item){
            window.open('/machines/view/'+item.item.id, '_blank');
            return false;
        },

        onFiltered(filteredItems) {
            // Trigger pagination to update the number of buttons/pages due to filtering
            this.totalRows = filteredItems.length
            this.currentPage = 1
        },
        async getList () {
            await axios.get(this.dataUrl)
            .then(response => {
                this.items = response.data.items
                this.fields = response.data.fields
                this.action_urls=response.data.action_urls
                this.messages=response.data.messages
                this.access=response.data.access
                this.totalRows = this.items.length
            })
            .catch(e => {
                console.log(e);
                toastr.error("Your internet connection has problem.","connnection error")
            })
        },
        showMsgBoxDelete(record) {
            this.msgBox = ''
            this.$bvModal.msgBoxConfirm('Please confirm that you want to delete this strategy.', {
            title: 'Please Confirm',
            size: 'sm',
            buttonSize: 'sm',
            okVariant: 'danger',
            okTitle: 'YES',
            cancelTitle: 'NO',
            footerClass: 'p-2',
            hideHeaderClose: false,
            centered: true
            })
            .then(value => {
                this.msgBox = value;
                if(value===true){
                    this.DeleteRecord(record);
                }
                else{
                    console.log(value);
                }
            })
            .catch(err => {
                // An error occurred
            })


        },
        changeCalView(item){
            if(this.access){
                this.UpdateRecord(item.item);
            }
            else{
                toastr.error(this.messages.update.access.text, this.messages.update.access.title)
            }

        },
        edit(item){
            window.open('/machines/edit/'+item.item.id, '_self');
            return false;
        }

    }
  }

</script>

