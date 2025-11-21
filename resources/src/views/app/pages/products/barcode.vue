<template>
  <div class="main-content">
    <breadcumb :page="$t('Printbarcode')" :folder="$t('Products')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <!-- <b-card v-if="!isLoading"> -->
      <b-row v-if="!isLoading">

        <b-modal hide-footer id="open_scan" size="md" title="Barcode Scanner">
          <qrcode-scanner
            :qrbox="250" 
            :fps="10" 
            style="width: 100%; height: calc(100vh - 56px);"
            @result="onScan"
          />
        </b-modal>

        <!-- Warehouse -->
        <b-col md="6" class="mb-3">
          <validation-observer ref="show_Barcode">
            <validation-provider name="warehouse" :rules="{ required: true}">
              <b-form-group slot-scope="{ valid, errors }" :label="$t('warehouse') + ' ' + '*'">
                <v-select
                  :class="{'is-invalid': !!errors.length}"
                  :state="errors[0] ? false : (valid ? true : null)"
                  @input="Selected_Warehouse"
                  v-model="barcode.warehouse_id"
                  :reduce="label => label.value"
                  :placeholder="$t('Choose_Warehouse')"
                  :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
                />
                <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
              </b-form-group>
            </validation-provider>
          </validation-observer>
        </b-col>

          <!-- Product -->
          <b-col md="12" class="mb-5">
            <h6>{{$t('ProductName')}}</h6>
            
            <div id="autocomplete" class="autocomplete">
              <div class="input-with-icon">
                      <img src="/assets_setup/scan.png" alt="Scan" class="scan-icon" @click="showModal">
              <input 
                :placeholder="$t('Scan_Search_Product_by_Code_Name')"
                @input='e => search_input = e.target.value' 
                @keyup="search(search_input)"
                @focus="handleFocus"
                @blur="handleBlur"
                ref="product_autocomplete"
                class="autocomplete-input" />
              </div>
              <ul class="autocomplete-result-list" v-show="focused">
                <li class="autocomplete-result" v-for="product_fil in product_filter" @mousedown="SearchProduct(product_fil)">{{getResultValue(product_fil)}}</li>
              </ul>
            </div>
          </b-col>

        <!-- Details Product  -->
        <b-col md="12">
          <div class="table-responsive">
            <table class="table table-hover table-md">
              <thead>
                <tr>
                  <th scope="col">{{$t('ProductName')}}</th>
                  <th scope="col">{{$t('CodeProduct')}}</th>
                  <th scope="col">{{$t('Quantity')}}</th>
                  <th scope="col" class="text-center">
                    <i class="fa fa-trash"></i>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="products_added.length === 0">
                  <td colspan="4">{{$t('NodataAvailable')}}</td>
                </tr>
                <tr v-for="product in products_added" :key="product.code">
                  <td>{{product.name}}</td>
                  <td>{{product.code}}</td>
                  <td>
                    <input
                      v-model.number="product.qte"
                      class="form-control w-50 h-25"
                      id="qte"
                      type="number"
                      name="qte"
                    >
                  </td>
                  <td>
                    <i @click="delete_Product(product.code)" class="i-Close-Window text-25 text-danger"></i>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </b-col>

         <!-- Paper_size  -->
          <b-col md="12">
            <b-form-group :label="$t('Paper_size')">
              <v-select
                v-model="paper_size"
                @input="Selected_Paper_size"
                :reduce="label => label.value"
                :placeholder="$t('Paper_size')"
                :options="
                      [
                        {label: '40 per sheet (a4) (1.799 * 1.003)', value: 'style40'},
                        {label: '30 per sheet (2.625 * 1)', value: 'style30'},
                        {label: '24 per sheet (a4) (2.48 * 1.334)', value: 'style24'},
                        {label: '20 per sheet (4 * 1)', value: 'style20'},
                        {label: '18 per sheet (a4) (2.5 * 1.835)', value: 'style18'},
                        {label: '14 per sheet (4 * 1.33)', value: 'style14'},
                        {label: '12 per sheet (a4) (2.5 * 2.834)', value: 'style12'},
                        {label: '10 per sheet (4 * 2)', value: 'style10'},
                        {label: 'Stickers', value: 'customstyle'},
                      ]"
              ></v-select>
            </b-form-group>
          </b-col>

          
          <b-col md="6" class="mt-4">
              <label class="checkbox checkbox-primary mb-3"><input type="checkbox" v-model="show_price"><h5>Display Price </h5><span class="checkmark"></span></label>
          </b-col>

        <b-col md="12" class="mt-4">
          <button @click="submit()" type="submit" class="btn btn-primary btn-sm m-1">
            <i class="i-Edit"></i>
            {{$t('Updat')}}
          </button>
          <button @click="reset()" type="submit" class="btn btn-danger btn-sm m-1">
            <i class="i-Power-2"></i>
            {{$t('Reset')}}
          </button>
          <button
            @click="print_all_Barcode()"
            value="Print"
            type="submit"
            class="btn btn-light btn-sm pull-right m-1"
          >
            <i class="i-Billing"></i>
            {{$t('print')}}
          </button>
        </b-col>
      
   
        <b-col md="12">
          <div class="barcode-row" v-if="ShowCard" id="print_barcode_label">
            <div v-for="(page, pageIndex) in pages" :key="pageIndex">
              <div :class="class_type_page">
                <div class="barcode-item" :class="class_sheet" v-for="(barcode, index) in page" :key="index">
                  <div class="head_barcode text-left" style="padding-left: 10px; font-weight: bold;font-size: 10px;">
                    <span class="barcode-name">{{barcode.name}}</span>
                    <span class="barcode-price" v-if="show_price">{{currentUser.currency}} {{barcode.Net_price}}</span>

                  </div>
                  <barcode
                    class="barcode"
                    :format="barcode.Type_barcode"
                    :value="barcode.barcode"
                    textmargin="0"
                    fontoptions="bold"
                    fontSize="15"
                    height="25"
                    width="1"
                  ></barcode>
                </div>
              </div>
            </div>
          </div>
        </b-col>


          </b-row>
          </div>
      
</template>

<script>
import VueBarcode from "vue-barcode";
import NProgress from "nprogress";
import { mapActions, mapGetters } from "vuex";

export default {
  components: {
    barcode: VueBarcode
  },
  data() {
    return {
      focused: false,
      timer:null,
      search_input:'',
      product_filter:[],
      isLoading: true,
      ShowCard: false,
      barcode: {
        product_id: "",
        warehouse_id: "",
        qte: 10
      },
      count: "",
      paper_size:"",
      sheets:'',
      total_a4:'',
      class_sheet:'',
      class_type_page:'',
      rest:'',     
      warehouses: [],
      submitStatus: null,
      show_price:true,
      products_added: [],
      pages: [],
      products: [],
      product: {
        name: "",
        code: "",
        Type_barcode: "",
        barcode:"",
        Net_price:"",
      }
    };
  },

  computed: {
    ...mapGetters(["currentUser"])
  },

  methods: {

    showModal() {
      this.$bvModal.show('open_scan');
      
    },

    onScan (decodedText, decodedResult) {
      const code = decodedText;
      this.search_input = code;
      this.search();
      this.$bvModal.hide('open_scan');
    },

    Per_Page(){
      this.total_a4 = parseInt(this.barcode.qte/this.sheets);
      this.rest = this.barcode.qte%this.sheets;
    },
 //---------------------- Event Selected_Paper_size------------------------------\\
    Selected_Paper_size(value) {
      if(value == 'style40'){
        this.sheets = 40;
        this.class_sheet = 'style40';
        this.class_type_page = 'barcodea4';
      }else if(value == 'style30'){
        this.sheets = 30;
        this.class_type_page = 'barcode_non_a4';
        this.class_sheet = 'style30';
      }else if(value == 'style24'){
        this.sheets = 24;
        this.class_sheet = 'style24';
       this.class_type_page = 'barcodea4';
      }else if(value == 'style20'){
        this.sheets = 20;
        this.class_sheet = 'style20';
        this.class_type_page = 'barcode_non_a4';
      }else if(value == 'style18'){
        this.sheets =  18;
        this.class_sheet = 'style18';
        this.class_type_page = 'barcodea4';
      }else if(value == 'style14'){
        this.sheets = 14;
        this.class_sheet = 'style14';
        this.class_type_page = 'barcode_non_a4';
      }else if(value == 'style12'){
        this.sheets = 12;
        this.class_sheet = 'style12';
       this.class_type_page = 'barcodea4';
      }else if(value == 'style10'){
        this.sheets = 10;
        this.class_sheet = 'style10';
       this.class_type_page = 'barcode_non_a4';
      }else if(value == 'customstyle'){
        this.sheets = 1;
        this.class_sheet = 'customstyle';
       this.class_type_page = 'barcode_custom';
      }
     
      this.Per_Page();
    },
    //------ Validate Form
    submit() {
      this.$refs.show_Barcode.validate().then(success => {
        if (!success) {
          return;
        } else {
         
          this.showBarcode();
          // this.Per_Page();
        }
      });
    },
    //---Validate State Fields
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },
      handleFocus() {
      this.focused = true
    },
    handleBlur() {
      this.focused = false
    },

      //-----------------------------------Delete Product ------------------------------\\
      delete_Product(code) {
      for (var i = 0; i < this.products_added.length; i++) {
        if (code === this.products_added[i].code) {
          this.products_added.splice(i, 1);
        }
      }
    },
    
   // Search Products
    search(){
      if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
      }
      if (this.search_input.length < 2) {
        return this.product_filter= [];
      }
      if (this.barcode.warehouse_id != "" &&  this.barcode.warehouse_id != null) {
          this.timer = setTimeout(() => {
          const product_filter = this.products.filter(product => product.code === this.search_input || product.barcode.includes(this.search_input));
            if(product_filter.length === 1){
                this.SearchProduct(product_filter[0])
            }else{
              let tokens = this.search_input.toLowerCase().split(' ');
                this.product_filter=  this.products.filter(product => {

                  return tokens.every(token =>
                      product.name.toLowerCase().includes(token) 
                      ||  product.code.toLowerCase().includes(token)
                      ||  product.barcode.toLowerCase().includes(token)
                      ||  (product.note && product.note.toLowerCase().includes(token))
                  );
                // this.product_filter=  this.products.filter(product => {
                //   return (
                //     product.name.toLowerCase().includes(this.search_input.toLowerCase()) ||
                //     product.code.toLowerCase().includes(this.search_input.toLowerCase()) ||
                //     product.barcode.toLowerCase().includes(this.search_input.toLowerCase())
                //     );
                });
                 // Check if product_filter is empty and show alert
                 if (this.product_filter.length <= 0) {
                  this.makeToast(
                    "warning",
                    "Product Not Found",
                    "Warning"
                  );

                }
            }
        }, 800);
      } else {
        this.makeToast(
          "warning",
          this.$t("SelectWarehouse"),
          this.$t("Warning")
        );
      }
    },
    //------ Search Result value
    getResultValue(result) {
      return result.code + " " + "(" + result.name + ")";
    },
   
     //------ Submit Search Product
     SearchProduct(result) {
      const existingProduct = this.products_added.find(product => product.code === result.code);

      if (existingProduct) {
        this.makeToast("warning", this.$t("AlreadyAdd"), this.$t("Warning"));
      } else {
        this.products_added.push({
          code: result.code,
          barcode: result.barcode,
          name: result.name,
          Type_barcode: result.Type_barcode,
          Net_price: result.Net_price,
          qte: 1, // Default quantity
        });
      }

      this.search_input = '';
      this.$refs.product_autocomplete.value = "";
      this.product_filter = [];
    },
    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },
    //------------------------------------ Get Products By Warehouse -------------------------\\
    Get_Products_By_Warehouse(id) {
      // Start the progress bar.
        NProgress.start();
        NProgress.set(0.1);
      axios
        .get("get_Products_by_warehouse/" + id + "?stock=" + 0 + "&product_service=" + 1 + "&product_combo=" + 1)
         .then(response => {
            this.products = response.data;
             NProgress.done();
            })
          .catch(error => {
          });
    },
    //-------------------------------------- Print Barcode -------------------------\\
    print_all_Barcode() {
      var divContents = document.getElementById("print_barcode_label").innerHTML;
      var a = window.open("", "", "height=500, width=500");
      a.document.write(
        '<link rel="stylesheet" href="/assets_setup/css/print_label.css"><html>'
      );
      a.document.write("<body >");
      a.document.write(divContents);
      a.document.write("</body></html>");
      a.document.close();

      setTimeout(() => {
         a.print();
      }, 1000);

      
    },

    generatePages() {
      let allBarcodes = [];
      this.products_added.forEach(product => {
        for (let i = 0; i < product.qte; i++) {
          allBarcodes.push({
            name: product.name,
            barcode: product.barcode,
            Type_barcode: product.Type_barcode,
            Net_price: product.Net_price
          });
        }
      });

      this.pages = [];
      while (allBarcodes.length > 0) {
        this.pages.push(allBarcodes.splice(0, this.sheets));
      }
    },
   
    //-------------------------------------- Show Barcode -------------------------\\
    showBarcode() {
      // this.Per_Page();
      // this.count = this.barcode.qte;
      this.generatePages();
      this.ShowCard = true;
    },
    //---------------------- Event Select Warehouse ------------------------------\\
    Selected_Warehouse(value) {
      this.search_input= '';
      this.product_filter = [];
      this.Get_Products_By_Warehouse(value);
    },
    //----------------------------------- GET Barcode Elements -------------------------\\
    Get_Elements: function() {
      axios
        .get("barcode_create_page")
        .then(response => {
          this.warehouses = response.data.warehouses;
          this.isLoading = false;
        })
        .catch(response => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },
    //----------------------------------- Reset Data -------------------------\\
    reset() {
      this.ShowCard = false;
      this.products = [];
      this.product.name = "";
      this.product.code = "";
      this.product.Net_price = "";
      this.barcode.qte = 10;
      this.count = 10;
      this.barcode.warehouse_id = "";
      this.search_input= '';
      this.$refs.product_autocomplete.value = "";
      this.product_filter = [];
    }
  }, //end Methods
  //-----------------------------Created function-------------------
  created: function() {
    this.Get_Elements();
  }
};
</script>


<style>

  .input-with-icon {
    display: flex;
    align-items: center;
  }

  .scan-icon {
    width: 50px; /* Adjust size as needed */
    height: 50px;
    margin-right: 8px; /* Adjust spacing as needed */
    cursor: pointer;
  }
</style>