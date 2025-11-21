<template>
    <div class="main-content">
      <breadcumb :page="$t('Sales_by_Category')" :folder="$t('Reports')"/>
  
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
      </b-col>

      <b-card class="wrapper" v-if="!isLoading">
        <vue-good-table
          mode="remote"
          :columns="columns"
          :totalRows="totalRows"
          :rows="rows"
          :group-options="{
            enabled: true,
            headerPosition: 'bottom',
          }"
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
          styleClass="tableOne table-hover vgt-table mt-3"
        >
  
         <div slot="table-actions" class="mt-2 mb-3">
          
            <b-button @click="report_pdf()" size="sm" variant="outline-success ripple m-1">
              <i class="i-File-Copy"></i> PDF
            </b-button>
             <vue-excel-xlsx
                class="btn btn-sm btn-outline-danger ripple m-1"
                :data="reports"
                :columns="columns"
                :file-name="'sales_by_category_report'"
                :file-type="'xlsx'"
                :sheet-name="'sales_by_category_report'"
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
  import { mapGetters } from "vuex";

  import DateRangePicker from 'vue2-daterange-picker'
  //you need to import the CSS manually
  import 'vue2-daterange-picker/dist/vue2-daterange-picker.css'
  import moment from 'moment'
  
  export default {
    components: { DateRangePicker },
    metaInfo: {
      title: "Sales By Category"
    },
    data() {
      return {
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
          today_mode: true,
          to: "",
          from: "",
        isLoading: true,
        rows: [{
          category_name: 'Total',
         
          children: [
             
          ],
      },],
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
        currency: "",
        reports: [],
        report: {},
        warehouse_id: 0
      };
    },
  
    computed: {
      ...mapGetters(["currentUser"]),
      columns() {
        return [
          {
            label: this.$t("Categorie"),
            field: "category_name",
            tdClass: "text-left",
            thClass: "text-left",
            sortable: false
          },
         
          {
            label: this.$t("total_sales"),
            field: "total_sales",
            // type: "decimal",
            headerField: this.sumCount,
            tdClass: "text-left",
            thClass: "text-left",
            sortable: false
          },

         
        ];
      }
    },
  
    methods: {

      
     sumCount(rowObj) {
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        sum += rowObj.children[i].total_sales;
      }
      return sum.toFixed(2) + ' ' + this.currency;
    },




  
       //----------------------------------- Sales PDF ------------------------------\\
      report_pdf() {
        var self = this;
        let pdf = new jsPDF("p", "pt");

        const fontPath = "/fonts/Vazirmatn-Bold.ttf";
        pdf.addFont(fontPath, "VazirmatnBold", "bold"); 
        pdf.setFont("VazirmatnBold"); 

        let columns = [
          { title: self.$t("Categorie"), dataKey: "category_name" },
          { title: self.$t("total_sales"), dataKey: "total_sales" },
        ];

        // Calculate totals
        let totalGrandTotal = self.reports.reduce((sum, report) => sum + parseFloat(report.total_sales || 0), 0);
        
        let footer = [{
          category_name: self.$t("Total"),
          total_sales: `${totalGrandTotal.toFixed(2)}`,
          
        }];

        pdf.autoTable({
             columns: columns,
             body: self.reports,
             foot: footer,
             startY: 70,
             theme: "grid", 
             didDrawPage: (data) => {
               pdf.setFont("VazirmatnBold");
               pdf.setFontSize(18);
               pdf.text("Sales by Category", 40, 25);   
             },
             styles: {
               font: "VazirmatnBold", 
               halign: "center", // 
             },
             headStyles: {
               fillColor: [200, 200, 200], 
               textColor: [0, 0, 0], 
               fontStyle: "bold", 
             },
             footStyles: {
               fillColor: [230, 230, 230], 
               textColor: [0, 0, 0], 
               fontStyle: "bold", 
             },
        });

        pdf.save("Sales_by_Category.pdf");
        
      },
  
      //---- update Params Table
      updateParams(newProps) {
        this.serverParams = Object.assign({}, this.serverParams, newProps);
      },
  
      //---- Event Page Change
      onPageChange({ currentPage }) {
        if (this.serverParams.page !== currentPage) {
          this.updateParams({ page: currentPage });
          this.get_sales_by_category(currentPage);
        }
      },
  
      //---- Event Per Page Change
      onPerPageChange({ currentPerPage }) {
        if (this.limit !== currentPerPage) {
          this.limit = currentPerPage;
          this.updateParams({ page: 1, perPage: currentPerPage });
          this.get_sales_by_category(1);
        }
      },
  
      //---- Event on Sort Change
      onSortChange(params) {
        this.updateParams({
          sort: {
            type: params[0].type,
            field: params[0].field
          }
        });
        this.get_sales_by_category(this.serverParams.page);
      },
  
      //---- Event on Search
  
      onSearch(value) {
        this.search = value.searchTerm;
        this.get_sales_by_category(this.serverParams.page);
      },
  
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
  
    //----------------------------- Submit Date Picker -------------------\\
    Submit_filter_dateRange() {
      var self = this;
      self.startDate =  self.dateRange.startDate.toJSON().slice(0, 10);
      self.endDate = self.dateRange.endDate.toJSON().slice(0, 10);
      self.get_sales_by_category(1);
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

  
      //--------------------------- Get Customer Report -------------\\
  
      get_sales_by_category(page) {
        // Start the progress bar.
        NProgress.start();
        NProgress.set(0.1);
        this.get_data_loaded();
        axios
          .get(
            "report/sales_by_category_report?page=" +
              page +
              "&SortField=" +
              this.serverParams.sort.field +
              "&SortType=" +
              this.serverParams.sort.type +
              "&search=" +
              this.search +
              "&limit=" +
              this.limit +
              "&to=" +
            this.endDate +
            "&from=" +
            this.startDate
          )
          .then(response => {
            this.reports = response.data.reports;
            this.totalRows = response.data.totalRows;
            this.currency = response.data.currency;
            this.rows[0].children = this.reports;

            // Complete the animation of theprogress bar.
            NProgress.done();
            this.isLoading = false;
            this.today_mode = false;
          })
          .catch(response => {
            // Complete the animation of theprogress bar.
            NProgress.done();
            setTimeout(() => {
              this.isLoading = false;
              this.today_mode = false;
            }, 500);
          });
      }
    }, //end Methods
  
    //----------------------------- Created function------------------- \\
  
    created: function() {
      this.get_sales_by_category(1);
    }
  };
  </script>