<template>
    <div class="main-content">
      <breadcumb :page="$t('Inventory_Valuation')" :folder="$t('Reports')"/>
  
      <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
      <b-card class="wrapper" v-if="!isLoading">
        <div class="col-6 alert alert-info">{{$t('Inventory_Valuation_Based_on_Average_Cost')}}</div>
        <vue-good-table
          mode="remote"
          :columns="columns"
          :totalRows="totalRows"
          :rows="reports"
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
  
         <div slot="table-actions" class="mt-2 mb-3 quantity_alert_warehouse">
          <!-- warehouse -->
          <b-form-group :label="$t('warehouse')">
            <v-select
              @input="Selected_Warehouse"
              v-model="warehouse_id"
              :reduce="label => label.value"
              :placeholder="$t('Choose_Warehouse')"
              :options="[
                { label: $t('All_Warehouses'), value: 0 }, // Fixed option for all warehouses
                ...warehouses.map(warehouse => ({ label: warehouse.name, value: warehouse.id }))
              ]"
            />
          </b-form-group>
        </div>
  
         <div slot="table-actions" class="mt-2 mb-3">
          
            <b-button @click="stock_report_PDF()" size="sm" variant="outline-success ripple m-1">
              <i class="i-File-Copy"></i> PDF
            </b-button>
           
          </div>
        </vue-good-table>
      </b-card>
    </div>
  </template>
  
  
  <script>
  import NProgress from "nprogress";
  import jsPDF from "jspdf";
  import "jspdf-autotable";
  
  export default {
    metaInfo: {
      title: "Inventory Valuation Summary"
    },
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
        reports: [],
        report_pdf: [],
        report: {},
        warehouses: [],
        warehouse_id: 0
      };
    },
  
    computed: {
      columns() {
        return [
          {
            label: this.$t("ITEM_NAME"),
            field: "name",
            tdClass: "text-left",
            thClass: "text-left",
            sortable: false
          },
          {
            label: this.$t("SKU"),
            field: "code",
            tdClass: "text-left",
            thClass: "text-left",
            sortable: false
          },
  
          {
            label: this.$t("Variant_NAME"),
            field: "variant_name",
            html: true,
            tdClass: "text-left",
            thClass: "text-left",
            sortable: false
          },
        
          {
            label: this.$t("STOCK_ON_HAND"),
            field: "stock_hand",
            html: true,
            tdClass: "text-left",
            thClass: "text-left",
            sortable: false
          },
  
          {
            label: this.$t("ASSET_VALUE"),
            field: "inventory_value",
            html: true,
            tdClass: "text-left",
            thClass: "text-left",
            sortable: false
          },
         
        ];
      }
    },
  
    methods: {
  
       //----------------------------------- Sales PDF ------------------------------\\
      stock_report_PDF() {
        var self = this;
        let pdf = new jsPDF("p", "pt");

        const fontPath = "/fonts/Vazirmatn-Bold.ttf";
        pdf.addFont(fontPath, "VazirmatnBold", "bold"); 
        pdf.setFont("VazirmatnBold"); 

        let columns = [
          { title: self.$t("ITEM_NAME"), dataKey: "name" },
          { title: self.$t("SKU"), dataKey: "code" },
          { title: self.$t("Variant_NAME"), dataKey: "variant_name" },
          { title: self.$t("STOCK_ON_HAND"), dataKey: "stock_hand" },
          { title: self.$t("ASSET_VALUE"), dataKey: "inventory_value" },
        ];
        
        // Create a copy of self.reports for PDF generation
        let report_pdf = JSON.parse(JSON.stringify(self.reports));
  
        // Initialize totals
        let totalstock_hand = 0;
        let totalinventory_value = 0;

        // Replace <br /> with newline character '\n' in the 'name' property of each item
        report_pdf.forEach(item => {
          item.variant_name = item.variant_name.replace(/<br\s*\/?>/gi, '\n');
          item.stock_hand = item.stock_hand.replace(/<br\s*\/?>/gi, '\n');
          item.inventory_value = item.inventory_value.replace(/<br\s*\/?>/gi, '\n');

          // Replace <br /> with newline, then parse and sum the values
          let stock_hand = item.stock_hand.replace(/<br\s*\/?>/gi, '\n').split('\n').reduce((sum, value) => sum + parseFloat(value || 0), 0);
          let inventory_value = item.inventory_value.replace(/<br\s*\/?>/gi, '\n').split('\n').reduce((sum, value) => sum + parseFloat(value || 0), 0);
  

        // Update totals
          totalstock_hand += parseFloat(stock_hand);
          totalinventory_value += parseFloat(inventory_value);
        });

      
     
     let footer = [{
      name: self.$t("Total"),
      code: '',
      variant_name: '',
      stock_hand: `${totalstock_hand.toFixed(2)}`,
      inventory_value: `${totalinventory_value.toFixed(2)}`,
     }];


     pdf.autoTable({
             columns: columns,
             body: report_pdf,
             foot: footer,
             startY: 70,
             theme: "grid", 
             didDrawPage: (data) => {
               pdf.setFont("VazirmatnBold");
               pdf.setFontSize(18);
               pdf.text("Inventory Valuation Summary", 40, 25);   
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

     pdf.save("Inventory_Valuation_Summary.pdf");

    },
  
      //---- update Params Table
      updateParams(newProps) {
        this.serverParams = Object.assign({}, this.serverParams, newProps);
      },
  
      //---- Event Page Change
      onPageChange({ currentPage }) {
        if (this.serverParams.page !== currentPage) {
          this.updateParams({ page: currentPage });
          this.Get_Stock_Report(currentPage);
        }
      },
  
      //---- Event Per Page Change
      onPerPageChange({ currentPerPage }) {
        if (this.limit !== currentPerPage) {
          this.limit = currentPerPage;
          this.updateParams({ page: 1, perPage: currentPerPage });
          this.Get_Stock_Report(1);
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
        this.Get_Stock_Report(this.serverParams.page);
      },
  
      //---- Event on Search
  
      onSearch(value) {
        this.search = value.searchTerm;
        this.Get_Stock_Report(this.serverParams.page);
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
  
       //---------------------- Event Select Warehouse ------------------------------\\
      Selected_Warehouse(value) {
        if (value === null) {
          this.warehouse_id = "";
        }
        this.Get_Stock_Report(1);
      },
  
      //--------------------------- Get Customer Report -------------\\
  
      Get_Stock_Report(page) {
        // Start the progress bar.
        NProgress.start();
        NProgress.set(0.1);
        axios
          .get(
            "report/inventory_valuation_summary?page=" +
              page +
              "&SortField=" +
              this.serverParams.sort.field +
              "&SortType=" +
              this.serverParams.sort.type +
              "&warehouse_id=" +
              this.warehouse_id +
              "&search=" +
              this.search +
              "&limit=" +
              this.limit
          )
          .then(response => {
            this.reports = response.data.reports;
            this.totalRows = response.data.totalRows;
            this.warehouses = response.data.warehouses;
            // Complete the animation of theprogress bar.
            NProgress.done();
            this.isLoading = false;
          })
          .catch(response => {
            // Complete the animation of theprogress bar.
            NProgress.done();
            setTimeout(() => {
              this.isLoading = false;
            }, 500);
          });
      }
    }, //end Methods
  
    //----------------------------- Created function------------------- \\
  
    created: function() {
      this.Get_Stock_Report(1);
    }
  };
  </script>