<template>
  <!-- ============ Body content start ============= -->
  <div class="main-content">
    <div v-if="loading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else-if="!loading && currentUserPermissions && currentUserPermissions.includes('dashboard')">
      <!-- Top filters -->
      <b-row class="mb-4 align-items-end">
        <!-- Warehouse -->
        <b-col lg="4" md="6" sm="12" class="mb-2">
          <v-select
            @input="Selected_Warehouse"
            v-model="warehouse_id"
            :reduce="label => label.value"
            :placeholder="$t('Filter_by_warehouse')"
            :options="warehouses.map(w => ({label:w.name, value:w.id}))"
          />
        </b-col>

        <!-- Date range -->
        <b-col lg="4" md="6" sm="12" class="mb-2">
          <label class="mb-1 d-block text-muted">{{ $t('DateRange') }}</label>
          <date-range-picker
            v-model="dateRange"
            :startDate="dateRange.startDate"
            :endDate="dateRange.endDate"
            :locale-data="locale"
            :autoApply="true"
            :showDropdowns="true"
            @update="Submit_filter_dateRange"
          >
            <template v-slot:input="picker">
              <b-button variant="light" class="w-100 text-left">
                <i class="i-Calendar-4 mr-1"></i>
                {{ fmt(picker.startDate) }} - {{ fmt(picker.endDate) }}
              </b-button>
            </template>
          </date-range-picker>
        </b-col>

        <!-- Quick ranges -->
        <b-col lg="4" md="12" sm="12" class="mb-2">
          <label class="mb-1 d-block text-muted">{{$t('QuickRanges')}}</label>
          <div class="btn-group flex-wrap quick-wrap">
            <b-button size="sm" variant="outline-primary" class="mr-1 mb-1" @click="quick('today')">{{ $t('Today') }}</b-button>
            <b-button size="sm" variant="outline-primary" class="mr-1 mb-1" @click="quick('7d')">7D</b-button>
            <b-button size="sm" variant="outline-primary" class="mr-1 mb-1" @click="quick('30d')">30D</b-button>
            <b-button size="sm" variant="outline-primary" class="mr-1 mb-1" @click="quick('90d')">90D</b-button>
            <b-button size="sm" variant="outline-primary" class="mr-1 mb-1" @click="quick('mtd')">{{$t('MTD')}}</b-button>
            <b-button size="sm" variant="outline-primary" class="mb-1"      @click="quick('ytd')">{{$t('YTD')}}</b-button>
          </div>
        </b-col>
      </b-row>

      <!-- Your existing content below (unchanged) -->
      <b-row>
        <!-- ICON BG -->
        <b-col lg="3" md="6" sm="12">
          <router-link tag="a" class to="/app/sales/list">
            <b-card class="card-icon-bg card-icon-bg-primary o-hidden mb-30 text-center">
              <i class="i-Full-Cart"></i>
              <div class="content">
                <p class="text-muted mt-2 mb-0">{{$t('Sales')}}</p>
                <p class="text-primary text-24 line-height-1 mb-2">
                  {{currentUser.currency}} {{report_today.today_sales?report_today.today_sales:0}}
                </p>
              </div>
            </b-card>
          </router-link>
        </b-col>

        <b-col lg="3" md="6" sm="12">
          <router-link tag="a" class to="/app/purchases/list">
            <b-card class="card-icon-bg card-icon-bg-primary o-hidden mb-30 text-center">
              <i class="i-Add-Cart"></i>
              <div class="content">
                <p class="text-muted mt-2 mb-0">{{$t('Purchases')}}</p>
                <p class="text-primary text-24 line-height-1 mb-2">
                  {{currentUser.currency}} {{report_today.today_purchases?report_today.today_purchases:0}}
                </p>
              </div>
            </b-card>
          </router-link>
        </b-col>

        <b-col lg="3" md="6" sm="12">
          <router-link tag="a" class to="/app/sale_return/list">
            <b-card class="card-icon-bg card-icon-bg-primary o-hidden mb-30 text-center">
              <i class="i-Right-4"></i>
              <div class="content">
                <p class="text-muted mt-2 mb-0">{{$t('SalesReturn')}}</p>
                <p class="text-primary text-24 line-height-1 mb-2">
                  {{currentUser.currency}} {{report_today.return_sales?report_today.return_sales:0}}
                </p>
              </div>
            </b-card>
          </router-link>
        </b-col>

        <b-col lg="3" md="6" sm="12">
          <router-link tag="a" class to="/app/purchase_return/list">
            <b-card class="card-icon-bg card-icon-bg-primary o-hidden mb-30 text-center">
              <i class="i-Left-4"></i>
              <div class="content">
                <p class="text-muted mt-2 mb-0">{{$t('PurchasesReturn')}}</p>
                <p class="text-primary text-24 line-height-1 mb-2">
                  {{currentUser.currency}} {{report_today.return_purchases?report_today.return_purchases:0}}
                </p>
              </div>
            </b-card>
          </router-link>
        </b-col>
      </b-row>

      <b-row>
        <b-col lg="8" md="12" sm="12">
          <b-card class="mb-30">
            <h4 class="card-title m-0">{{$t('This_Week_Sales_Purchases')}}</h4>
            <div class="chart-wrapper">
              <div v-once class="typo__p text-right" v-if="loading">
                <div class="spinner sm spinner-primary mt-3"></div>
              </div>
              <v-chart v-if="!loading" :options="echartSales" :autoresize="true"></v-chart>
            </div>
          </b-card>
        </b-col>
        <b-col col lg="4" md="12" sm="12">
          <b-card class="mb-30">
            <h4 class="card-title m-0">{{$t('Top_Selling_Products')}} ({{new Date().getFullYear()}})</h4>
            <div class="chart-wrapper">
              <div v-once class="typo__p text-right" v-if="loading">
                <div class="spinner sm spinner-primary mt-3"></div>
              </div>
              <v-chart v-if="!loading" :options="echartProduct" :autoresize="true"></v-chart>
            </div>
          </b-card>
        </b-col>
      </b-row>

      <b-row>
        <!-- Stock Alert -->
        <div class="col-md-8">
          <div class="card mb-30">
            <div class="card-body p-2">
              <h5 class="card-title border-bottom p-3 mb-2">{{$t('StockAlert')}}</h5>

              <vue-good-table
                :columns="columns_stock"
                styleClass="order-table vgt-table mb-3"
                row-style-class="text-left"
                :rows="stock_alerts"
              >
                <template slot="table-row" slot-scope="props">
                  <div v-if="props.column.field == 'stock_alert'">
                    <span class="badge badge-outline-danger">{{props.row.stock_alert}}</span>
                  </div>
                </template>
              </vue-good-table>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card mb-30">
            <div class="card-body p-3">
              <h5 class="card-title border-bottom p-3 mb-2">
                {{$t('Top_Selling_Products')}} ({{CurrentMonth}})
              </h5>

              <vue-good-table
                :columns="columns_products"
                styleClass="order-table vgt-table"
                row-style-class="text-left"
                :rows="products"
              >
                <template slot="table-row" slot-scope="props">
                  <div v-if="props.column.field == 'total'">
                    <span>{{currentUser.currency}} {{formatNumber(props.row.total ,2)}}</span>
                  </div>
                </template>
              </vue-good-table>
            </div>
          </div>
        </div>
      </b-row>

      <b-row>
        <b-col lg="8" md="12" sm="12">
          <b-card class="mb-30">
            <h4 class="card-title m-0">{{$t('Payment_Sent_Received')}}</h4>
            <div class="chart-wrapper">
              <v-chart :options="echartPayment" :autoresize="true"></v-chart>
            </div>
          </b-card>
        </b-col>
        <b-col col lg="4" md="12" sm="12">
          <b-card class="mb-30">
            <h4 class="card-title m-0">{{$t('TopCustomers')}} ({{CurrentMonth}})</h4>
            <div class="chart-wrapper">
              <v-chart :options="echartCustomer" :autoresize="true"></v-chart>
            </div>
          </b-card>
        </b-col>
      </b-row>

      <!-- Last Sales -->
      <b-row>
        <div class="col-md-12">
          <div class="card mb-30">
            <div class="card-body p-0">
              <h5 class="card-title border-bottom p-3 mb-2">{{$t('Recent_Sales')}}</h5>

              <vue-good-table
                v-if="!loading"
                :columns="columns_sales"
                styleClass="order-table vgt-table"
                row-style-class="text-left"
                :rows="sales"
              >
                <template slot="table-row" slot-scope="props">
                  <div v-if="props.column.field == 'statut'">
                    <span v-if="props.row.statut == 'completed'" class="badge badge-outline-success">{{$t('complete')}}</span>
                    <span v-else-if="props.row.statut == 'pending'" class="badge badge-outline-info">{{$t('Pending')}}</span>
                    <span v-else class="badge badge-outline-warning">{{$t('Ordered')}}</span>
                  </div>

                  <div v-else-if="props.column.field == 'payment_status'">
                    <span v-if="props.row.payment_status == 'paid'" class="badge badge-outline-success">{{$t('Paid')}}</span>
                    <span v-else-if="props.row.payment_status == 'partial'" class="badge badge-outline-primary">{{$t('partial')}}</span>
                    <span v-else class="badge badge-outline-warning">{{$t('Unpaid')}}</span>
                  </div>
                </template>
              </vue-good-table>
            </div>
          </div>
        </div>
      </b-row>
    </div>

    <div v-else>
      <h4>{{$t('Welcome_to_your_Dashboard')}}</h4>
    </div>
  </div>
  <!-- ============ Body content End ============= -->
</template>

<script>
import { mapGetters } from "vuex";
import ECharts from "vue-echarts/components/ECharts.vue";
import DateRangePicker from "vue2-daterange-picker";
import "vue2-daterange-picker/dist/vue2-daterange-picker.css";
import moment from "moment";

// ECharts modules (unchanged)
import "echarts/lib/chart/pie";
import "echarts/lib/chart/bar";
import "echarts/lib/chart/line";
import "echarts/lib/component/tooltip";
import "echarts/lib/component/legend";

export default {
  components: {
    "v-chart": ECharts,
    "date-range-picker": DateRangePicker,
  },
  metaInfo: { title: "Dashboard" },
  data() {
    // default: today
    const end = moment().endOf("day");
    const start = end.clone().startOf("day");
    return {
      // date filter
      dateRange: { startDate: start.toDate(), endDate: end.toDate() },
      startDate: start.format("YYYY-MM-DD"),
      endDate: end.format("YYYY-MM-DD"),
      locale: {
        Label: "Apply",
        cancelLabel: "Cancel",
        weekLabel: "W",
        customRangeLabel: "Custom Range",
        daysOfWeek: moment.weekdaysMin(),
        monthNames: moment.monthsShort(),
        firstDay: 1
      },
      today_mode: true,

      // other state
      to: "",
      from: "",
      sales: [],
      warehouses: [],
      warehouse_id: "",
      stock_alerts: [],
      report_today: {
        revenue: 0,
        today_purchases: 0,
        today_sales: 0,
        return_sales: 0,
        return_purchases: 0
      },
      products: [],
      CurrentMonth: "",
      loading: true,

      // charts (leave as you had)
      echartSales: {},
      echartProduct: {},
      echartCustomer: {},
      echartPayment: {}
    };
  },
  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"]),
    // (tables unchanged)
    columns_sales() {
      return [
        { label: this.$t("Reference"), field: "Ref", tdClass: "gull-border-none text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Customer"), field: "client_name", tdClass: "gull-border-none text-left", thClass: "text-left", sortable: false },
        { label: this.$t("warehouse"), field: "warehouse_name", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Status"), field: "statut", html: true, tdClass: "gull-border-none text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Total"), field: "GrandTotal", type: "decimal", tdClass: "gull-border-none text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Paid"), field: "paid_amount", type: "decimal", tdClass: "gull-border-none text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Due"), field: "due", type: "decimal", tdClass: "gull-border-none text-left", thClass: "text-left", sortable: false },
        { label: this.$t("PaymentStatus"), field: "payment_status", html: true, sortable: false, tdClass: "text-left gull-border-none", thClass: "text-left" }
      ];
    },
    columns_stock() {
      return [
        { label: this.$t("ProductCode"), field: "code", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("ProductName"), field: "name", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("warehouse"), field: "warehouse", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Quantity"), field: "quantity", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("AlertQuantity"), field: "stock_alert", tdClass: "text-left", thClass: "text-left", sortable: false }
      ];
    },
    columns_products() {
      return [
        { label: this.$t("ProductName"), field: "name", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("TotalSales"), field: "total_sales", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("TotalAmount"), field: "total", tdClass: "text-left", thClass: "text-left", sortable: false }
      ];
    }
  },
  methods: {
    fmt(d){ return moment(d).format("YYYY-MM-DD"); },

    // Quick ranges
    quick(key){
      const end = moment().endOf("day");
      let start = end.clone();
      switch (key) {
        case "today": start = end.clone().startOf("day"); break;
        case "7d":    start = end.clone().subtract(6, "days").startOf("day"); break;
        case "30d":   start = end.clone().subtract(29, "days").startOf("day"); break;
        case "90d":   start = end.clone().subtract(89, "days").startOf("day"); break;
        case "mtd":   start = moment().startOf("month"); break;
        case "ytd":   start = moment().startOf("year"); break;
      }
      this.dateRange = { startDate: start.toDate(), endDate: end.toDate() };
      this.startDate = start.format("YYYY-MM-DD");
      this.endDate   = end.format("YYYY-MM-DD");
      this.all_dashboard_data();
    },

    // Date range picker update
    Submit_filter_dateRange() {
      const s = moment(this.dateRange.startDate);
      const e = moment(this.dateRange.endDate);
      this.startDate = s.format("YYYY-MM-DD");
      this.endDate   = e.format("YYYY-MM-DD");
      this.all_dashboard_data();
    },

    get_data_loaded() {
      if (this.today_mode) {
        const end = moment().endOf("day");
        const start = end.clone().startOf("day");
        this.startDate = start.format("YYYY-MM-DD");
        this.endDate   = end.format("YYYY-MM-DD");
        this.dateRange = { startDate: start.toDate(), endDate: end.toDate() };
      }
    },

    Selected_Warehouse(value) {
      if (value === null) this.warehouse_id = "";
      this.all_dashboard_data();
    },

    //---------------------------------- Report Dashboard With Echart (unchanged)
    all_dashboard_data() {
      this.get_data_loaded();
      axios
        .get(`/dashboard_data?warehouse_id=${this.warehouse_id}&to=${this.endDate}&from=${this.startDate}`)
        .then(response => {
          this.today_mode = false;
          const responseData = response.data;

          this.report_today = response.data.report_dashboard.original.report;
          this.warehouses   = response.data.warehouses;
          this.stock_alerts = response.data.report_dashboard.original.stock_alert;
          this.products     = response.data.report_dashboard.original.products;
          this.sales        = response.data.report_dashboard.original.last_sales;

          // Charts config: KEEP YOUR EXISTING CODE (unchanged)
          var dark_heading = "#c2c6dc";
          this.echartCustomer = {
            color: ["#6D28D9", "#8B5CF6", "#A78BFA", "#C4B5FD", "#7C3AED"],
            tooltip: { show: true, backgroundColor: "rgba(0, 0, 0, .8)" },
            formatter: function(params) { return `${params.name}: (${params.data.value} sales) (${params.percent}%)`; },
            series: [{ name: "Top Customers", type: "pie", radius: "50%", center: "50%", data: responseData.customers.original,
              itemStyle: { emphasis: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: "rgba(0, 0, 0, 0.5)" } }
            }]
          };
          this.echartPayment = {
            tooltip: { trigger: "axis" },
            legend: { data: ["Payment sent", "Payment received"] },
            grid: { left: "3%", right: "4%", bottom: "3%", containLabel: true },
            toolbox: { feature: { saveAsImage: {} } },
            xAxis: { type: "category", boundaryGap: false, data: responseData.payments.original.days },
            yAxis: { type: "value" },
            series: [
              { name: "Payment sent", type: "line", data: responseData.payments.original.payment_sent },
              { name: "Payment received", type: "line", data: responseData.payments.original.payment_received }
            ]
          };
          this.echartProduct = {
            color: ["#6D28D9", "#8B5CF6", "#A78BFA", "#C4B5FD", "#7C3AED"],
            tooltip: { show: true, backgroundColor: "rgba(0, 0, 0, .8)" },
            formatter: function(params) { return `${params.name}: (${params.value}sales)`; },
            series: [{ name: "Top Selling Products", type: "pie", radius: "50%", center: "50%", data: responseData.product_report.original,
              itemStyle: { emphasis: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: "rgba(0, 0, 0, 0.5)" } }
            }]
          };
          this.echartSales = {
            legend: { borderRadius: 0, orient: "horizontal", x: "right", data: ["Sales", "Purchases"] },
            grid: { left: "8px", right: "8px", bottom: "0", containLabel: true },
            tooltip: { show: true, backgroundColor: "rgba(0, 0, 0, .8)" },
            xAxis: [{
              type: "category", data: responseData.sales.original.days,
              axisTick: { alignWithLabel: true }, splitLine: { show: false },
              axisLabel: { color: dark_heading, interval: 0, rotate: 30 },
              axisLine: { show: true, color: dark_heading, lineStyle: { color: dark_heading } }
            }],
            yAxis: [{
              type: "value",
              axisLabel: { color: dark_heading },
              axisLine: { show: false, color: dark_heading, lineStyle: { color: dark_heading } },
              min: 0, splitLine: { show: true, interval: "auto" }
            }],
            series: [
              { name: "Sales", data: responseData.sales.original.data, label: { show: false, color: "#8B5CF6" }, type: "bar", color: "#A78BFA", smooth: true,
                itemStyle: { emphasis: { shadowBlur: 10, shadowOffsetX: 0, shadowOffsetY: -2, shadowColor: "rgba(0, 0, 0, 0.3)" } }
              },
              { name: "Purchases", data: responseData.purchases.original.data, label: { show: false, color: "#0168c1" },
                type: "bar", barGap: 0, color: "#DDD6FE", smooth: true,
                itemStyle: { emphasis: { shadowBlur: 10, shadowOffsetX: 0, shadowOffsetY: -2, shadowColor: "rgba(0, 0, 0, 0.3)" } }
              }
            ]
          };

          this.loading = false;
        })
        .catch(() => { this.today_mode = false; });
    },

    GetMonth() {
      const months = ["January","February","March","April","May","June","July","August","September","October","November","December"];
      this.CurrentMonth = months[new Date().getMonth()];
    },

    formatNumber(number, dec) {
      const value = (typeof number === "string" ? number : number.toString()).split(".");
      if (dec <= 0) return value[0];
      let f = value[1] || "";
      if (f.length > dec) return `${value[0]}.${f.substr(0, dec)}`;
      while (f.length < dec) f += "0";
      return `${value[0]}.${f}`;
    }
  },
  async mounted() {
    await this.all_dashboard_data();
    this.GetMonth();
  }
};
</script>

<style scoped>
.quick-wrap .btn { min-width: 58px; }
</style>
