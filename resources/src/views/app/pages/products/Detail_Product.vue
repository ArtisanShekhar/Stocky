<template>
  <div class="main-content">
    <breadcumb :page="$t('ProductDetails')" :folder="$t('Products')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card no-body v-if="!isLoading">
      <b-card-header>
        <button @click="print_product()" class="btn btn-outline-primary">
          <i class="i-Billing"></i>
          {{$t('print')}}
        </button>
      </b-card-header>
      <b-card-body>
        <b-row id="print_product">
          <b-col md="12" class="mb-5" v-if="product.type != 'is_variant'">
            <barcode
              class="barcode"
              :format="product.Type_barcode"
              :value="product.code"
              textmargin="0"
              fontoptions="bold"
            ></barcode>
          </b-col>

          <b-col md="8">
            <table class="table table-hover table-bordered table-md">
              <tbody>
                 <tr>
                  <td>{{$t('type')}}</td>
                  <th>{{product.type_name}}</th>
                </tr>
                <tr>
                  <td>{{$t('Model Name')}}</td>
                  <th>{{product.code}}</th>
                </tr>
                <tr>
                  <td>{{$t('ProductName')}}</td>
                  <th>{{product.name}}</th>
                </tr>
                <tr>
                  <td>{{$t('Categorie')}}</td>
                  <th>{{product.category}}</th>
                </tr>
                <tr>
                  <td>{{$t('Brand')}}</td>
                  <th>{{product.brand}}</th>
                </tr>
                <tr v-if="product.type == 'is_single' || product.type == 'is_combo'">
                  <td>{{$t('Cost')}}</td>
                  <th>{{currentUser.currency}} {{formatNumber(product.cost ,2)}}</th>
                </tr>
                <tr v-if="product.type != 'is_variant'">
                  <td>{{$t('Price')}}</td>
                  <th>{{currentUser.currency}} {{formatNumber(product.price ,2)}}</th>
                </tr>
                <tr v-if="product.type != 'is_service'">
                  <td>{{$t('Unit')}}</td>
                  <th>{{product.unit}}</th>
                </tr>
                <tr>
                  <td>{{$t('Tax')}}</td>
                  <th>{{formatNumber(product.taxe ,2)}} %</th>
                </tr>
                <tr v-if="product.taxe != '0.00'">
                  <td>{{$t('TaxMethod')}}</td>
                  <th>{{product.tax_method}}</th>
                </tr>

                <tr>
                  <td>{{$t('Discount')}}</td>
                  <th>{{product.discount}}</th>
                </tr>

                <tr v-if="product.type != 'is_service'"> 
                  <td>{{$t('StockAlert')}}</td>
                  <th>
                    <span
                      class="badge badge-outline-warning"
                    >{{formatNumber(product.stock_alert ,2)}}</span>
                  </th>
                </tr>

                <!-- Warranty & Guarantee -->
                <tr v-if="product.warranty_period !== null">
                  <td>{{$t('Warranty_Period')}}</td>
                  <th>
                    {{product.warranty_period}}
                    {{$t(product.warranty_unit)}}
                  </th>
                </tr>
                <tr v-if="product.warranty_terms">
                  <td>{{$t('WarrantyTerms')}}</td>
                  <th>{{product.warranty_terms}}</th>
                </tr>
                <tr v-if="product.has_guarantee">
                  <td>{{$t('Guarantee_Period')}}</td>
                  <th>
                    {{product.guarantee_period}}
                    {{$t(product.guarantee_unit)}}
                  </th>
                </tr>
              </tbody>
            </table>
          </b-col>
          <b-col md="4" class="mb-30">
            <img :src="'/images/products/' + product.image" alt="Product Image" />
          </b-col>

            <!-- product combo -->
            <b-col md="5" class="mt-4" v-if="product.type == 'is_combo'">
            <h3>Combined Products</h3>
            <table class="table table-hover table-sm">
              <thead>
                <tr>
                  <th>Product Code</th>
                  <th>Product Name</th>
                  <th>Quantity</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="product_combo_data in product.products_combo_data">
                  <td>{{product_combo_data.code}}</td>
                  <td>{{product_combo_data.name}}</td>
                  <td>{{product_combo_data.quantity}}</td>
                </tr>
              </tbody>
            </table>
          </b-col>

          <!-- product variant -->
          <b-col md="5" class="mt-4" v-if="product.type == 'is_variant'">
            <table class="table table-hover table-sm">
              <thead>
                <tr>
                  <th>{{$t('Variant_code')}}</th>
                  <th>{{$t('Variant_Name')}}</th>
                  <th>{{$t('Variant_cost')}}</th>
                  <th>{{$t('Variant_price')}}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="product_variant_data in product.products_variants_data">
                  <td>{{product_variant_data.code}}</td>
                  <td>{{product_variant_data.name}}</td>
                  <td>{{currentUser.currency}} {{product_variant_data.cost}}</td>
                  <td>{{currentUser.currency}} {{product_variant_data.price}}</td>
                </tr>
              </tbody>
            </table>
          </b-col>

           <!-- Warehouse Quantity -->
          <b-col md="7" class="mt-4" v-if="product.type == 'is_single'">
            <table class="table table-hover table-sm">
              <thead>
                <tr>
                  <th>{{$t('warehouse')}}</th>
                  <th>{{$t('Quantity')}}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="PROD_W in product.CountQTY">
                  <td>{{PROD_W.mag}}</td>
                  <td>{{formatNumber(PROD_W.qte ,2)}} {{product.unit}}</td>
                </tr>
              </tbody>
            </table>
          </b-col>

          <!-- Warehouse Variants Quantity -->
          <b-col md="7" v-if="product.type == 'is_variant'" class="mt-4">
            <table class="table table-hover table-sm">
              <thead>
                <tr>
                  <th>{{$t('warehouse')}}</th>
                  <th>{{$t('Variant')}}</th>
                  <th>{{$t('Quantity')}}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="PROD_V in product.CountQTY_variants">
                  <td>{{PROD_V.mag}}</td>
                  <td>{{PROD_V.variant}}</td>
                  <td>{{formatNumber(PROD_V.qte ,2)}} {{product.unit}}</td>
                </tr>
              </tbody>
            </table>
          </b-col>
        </b-row>
        <hr v-show="product.note">
        <b-row class="mt-4">
           <b-col md="12">
             <p>{{product.note}}</p>
           </b-col>
        </b-row>
      </b-card-body>
    </b-card>
  </div>
</template>


<script>
import VueBarcode from "vue-barcode";
import { mapActions, mapGetters } from "vuex";

export default {
  metaInfo: {
    title: "Detail Product"
  },
  components: {
    barcode: VueBarcode
  },

  data() {
    return {
      len: 8,
      isLoading: true,
      product: {},
      roles: {},
      variants: []
    };
  },
  computed: {
    ...mapGetters(["currentUser"])
  },

  methods: {
   

    //------------------------------Formetted Numbers -------------------------\\
    formatNumber(number, dec) {
      const value = (typeof number === "string"
        ? number
        : number.toString()
      ).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec)
        return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}.${formated}`;
    },

     //------- printproduct
    print_product() {
       this.$htmlToPaper('print_product');
    },

    //----------------------------------- Get Details Product ------------------------------\\
    showDetails() {
      let id = this.$route.params.id;
      axios
        .get(`get_product_detail/${id}`)
        .then(response => {
          this.product = response.data;
          this.isLoading = false;
        })
        .catch(response => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    }
  }, //end Methods

  //-----------------------------Created function-------------------

  created: function() {
    this.showDetails();
  }
};
</script>