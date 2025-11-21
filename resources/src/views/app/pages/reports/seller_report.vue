<template>
  <div class="main-content">
    <breadcumb :page="$t('Seller_report')" :folder="$t('Reports')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

      <b-col md="12" class="text-center" v-if="!isLoading">
        <date-range-picker 
          v-model="dateRange" 
          :startDate="startDate" 
          :endDate="endDate" 
           @update="Submit_filter_dateRange"
          :locale-data="locale" > 

          <template v-slot:input="picker" style="min-width: 350px;">
              {{ picker.startDate.toJSON().slice(0, 10)}} - {{ picker.endDate.toJSON().slice(0, 10)}}
          </template> 

        </date-range-picker>
        <!-- Time Range Filters -->
        <b-row class="mt-2 justify-content-center">
          <b-col cols="5" md="3">
            <b-form-group label="Start Time">
              <b-form-input type="time" v-model="start_time" @change="Seller_report(1)" />
            </b-form-group>
          </b-col>
          <b-col cols="5" md="3">
            <b-form-group label="End Time">
              <b-form-input type="time" v-model="end_time" @change="Seller_report(1)" />
            </b-form-group>
          </b-col>
        </b-row>
      </b-col>


    <b-card class="wrapper" v-if="!isLoading">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="payments"
       
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
        placeholder: $t('Search_this_table'),
        enabled: true,
      }"
        :pagination-options="{
        enabled: true,
        mode: 'records',
        nextLabel: 'next',
        prevLabel: 'prev',
      }"
        styleClass="table-hover tableOne vgt-table"
      >
        <div slot="table-actions" class="mt-2 mb-3">

           <!-- warehouse -->
          <b-form-group :label="$t('warehouse')">
            <v-select
              @input="Selected_Warehouse"
              v-model="warehouse_id"
              :reduce="label => label.value"
              :placeholder="$t('Choose_Warehouse')"
              :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
            />
          </b-form-group>
         
          <b-button @click="Seller_report_pdf()" size="sm" variant="outline-success ripple m-1">
            <i class="i-File-Copy"></i> PDF
          </b-button>
          <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger ripple m-1"
              :data="payments"
              :columns="columns"
              :file-name="'Seller_report'"
              :file-type="'xlsx'"
              :sheet-name="'Seller_report'"
              >
              <i class="i-File-Excel"></i> EXCEL
          </vue-excel-xlsx>
        </div>
      </vue-good-table>

    </b-card>


  </div>
</template>


<script>
import NProgress from "nprogress";
import jsPDF from "jspdf";
import "jspdf-autotable";
import DateRangePicker from 'vue2-daterange-picker'
//you need to import the CSS manually
import 'vue2-daterange-picker/dist/vue2-daterange-picker.css'
import moment from 'moment'

export default {
  metaInfo: {
    title: "Report Seller"
  },
  components: { DateRangePicker },

  data() {
    return {
      isLoading: true,
      serverParams: {
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      limit: "10",
      search: "",
      totalRows: "",
      start_time: '',
      end_time: '',
      payments: [],
      paymentMethods: [],
      warehouse_id: "",
      today_mode: true,
      startDate: "", 
      endDate: "", 
      dateRange: { 
       startDate: "", 
       endDate: "" 
      }, 
      locale:{ 
          //separator between the two ranges apply
          Label: "Apply", 
          cancelLabel: "Cancel", 
          weekLabel: "W", 
          customRangeLabel: "Custom Range", 
          daysOfWeek: moment.weekdaysMin(), 
          //array of days - see moment documenations for details 
          monthNames: moment.monthsShort(), //array of month names - see moment documenations for details 
          firstDay: 1 //ISO first day of week - see moment documenations for details
        },
    };
  },

  computed: {
    columns() {
      const base = [
        {
          label: this.$t("Seller"),
          field: "username",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: true
        },
        {
          label: this.$t("TotalSales"),
          field: "total_sales",
          tdClass: "text-center",
          thClass: "text-center",
          sortable: false
        }
      ];

      const dynamic = this.paymentMethods.map(method => ({
        label: method,
        field: method,
        tdClass: "text-right",
        thClass: "text-right",
        sortable: false
      }));

      return [...base, ...dynamic];
    }


  },
  methods: {

     //---------------------- Event Select Warehouse ------------------------------\\
    Selected_Warehouse(value) {
      if (value === null) {
        this.warehouse_id = "";
      }
      this.Seller_report(1);
    },

    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Seller_report(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Seller_report(1);
      }
    },

    //---- Event on Sort Change
    onSortChange(params) {
      let field = "";
      field = params[0].field;
      this.updateParams({
        sort: {
          type: params[0].type,
          field: field
        }
      });
      this.Seller_report(this.serverParams.page);
    },

    //---- Event on Search

    onSearch(value) {
      this.search = value.searchTerm;
      this.Seller_report(this.serverParams.page);
    },


  Seller_report_pdf() {
    const pdf = new jsPDF("p", "pt");

    // Load custom font
    const fontPath = "/fonts/Vazirmatn-Bold.ttf";
    pdf.addFont(fontPath, "VazirmatnBold", "bold");
    pdf.setFont("VazirmatnBold");

   // 1. Base headers
  const headers = [
    { title: this.$t("Seller"), dataKey: "username" },
    { title: this.$t("TotalSales"), dataKey: "total_sales" },
    ...(this.paymentMethods || []).map(method => ({
      title: this.$t(method.replace(/\s+/g, "_")),
      dataKey: method
    }))
  ];

    // 2. Build rows
    const rows = this.payments;

   
    // 4. Generate PDF table
    pdf.autoTable({
    head: [headers.map(h => h.title)],
    body: rows.map(row => headers.map(h => row[h.dataKey] ?? '')),
    startY: 70,
    theme: "grid",
    didDrawPage: () => {
      pdf.setFontSize(18);
      pdf.text('Seller Payment Report', 40, 25);
    },
    styles: {
      halign: "center"
    },
    headStyles: {
      fillColor: [200, 200, 200],
      textColor: [0, 0, 0],
      fontStyle: "bold"
    }
  });


    // 5. Save file
    pdf.save("Seller_Payment_Report.pdf");
  },




     //----------------------------- Submit Date Picker -------------------\\
    Submit_filter_dateRange() {
      var self = this;
      self.startDate =  self.dateRange.startDate.toJSON().slice(0, 10);
      self.endDate = self.dateRange.endDate.toJSON().slice(0, 10);
      self.Seller_report(1);
    },


     get_data_loaded() {
      var self = this;
      if (self.today_mode) {
        let startDate = new Date("01/01/2000");  // Set start date to "01/01/2000"
        let endDate = new Date();  // Set end date to current date

        self.startDate = startDate.toISOString();
        self.endDate = endDate.toISOString();

        self.dateRange.startDate = startDate.toISOString();
        self.dateRange.endDate = endDate.toISOString();
      }
    },

    //-------------------------------- Get All Payments Sales ---------------------\\
    Seller_report(page) {
      // Start the progress bar
      NProgress.start();
      NProgress.set(0.1);

      // Mark loading
      this.get_data_loaded();

      axios
        .get("report/seller_report", {
          params: {
            page: page,
            SortField: this.serverParams.sort.field,
            SortType: this.serverParams.sort.type,
            search: this.search,
            limit: this.limit,
            warehouse_id: this.warehouse_id,
            end_date: this.endDate,
            start_date: this.startDate,
            start_time: this.start_time,
            end_time: this.end_time, 
          }
        })
        .then(response => {
          this.payments = response.data.report;
          this.paymentMethods = response.data.paymentMethods || [];
          this.warehouses = response.data.warehouses;
          this.totalRows = response.data.totalRows;

          NProgress.done();
          this.isLoading = false;
          this.today_mode = false;
        })
        .catch(error => {
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
            this.today_mode = false;
          }, 500);
        });
    }

  },

  //----------------------------- Created function-------------------\\
  created: function() {
    this.Seller_report(1);
  }
};
</script>