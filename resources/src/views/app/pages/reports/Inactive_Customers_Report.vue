<template>
  <div class="main-content">
    <breadcumb :page="$t('Inactive_Customers_Report')" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper" v-if="!isLoading">
      <!-- Period filter -->
      <div class="d-flex align-items-center mb-2">
        <label class="mb-0 mr-2">{{$t('Period')}}:</label>
        <b-form-select
          v-model="period"
          :options="periodOptions"
          size="sm"
          class="w-auto"
          @change="onPeriodChange"
        />
      </div>

      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="rows"
        :group-options="{ enabled: true, headerPosition: 'bottom' }"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{ placeholder: $t('Search_this_table'), enabled: true }"
        :pagination-options="{ enabled: true, mode: 'records', nextLabel: 'next', prevLabel: 'prev' }"
        styleClass="tableOne table-hover vgt-table mt-3"
      >
        <template slot="table-row" slot-scope="props">
          <!-- Render last purchase nicely -->
          <span v-if="props.column.field === 'last_sale_at'">
            {{ props.row.last_sale_at ? props.row.last_sale_at : '—' }}
          </span>

          <!-- Render days inactive / never purchased badge -->
          <span v-else-if="props.column.field === 'days_inactive'">
            <b-badge variant="warning" v-if="!props.row.last_sale_at">
              {{$t('Never_Purchased')}}
            </b-badge>
            <span v-else>{{ props.row.days_inactive }}</span>
          </span>

          <!-- Actions (reuse your existing PDF + detail link) -->
          <span v-else-if="props.column.field === 'actions'">
           
            <router-link title="Report" :to="'/app/reports/detail_customer/'+props.row.id">
              <i class="i-Eye text-25 text-info"></i>
            </router-link>
          </span>

          <span v-else>
            {{ props.formattedRow[props.column.field] }}
          </span>
        </template>
      </vue-good-table>
    </b-card>
  </div>
</template>

<script>
import NProgress from "nprogress";
import { mapGetters } from "vuex";
// axios assumed globally available as in your current code

export default {
  metaInfo: {
    title: "Inactive Customers Report"
  },
  data() {
    return {
      isLoading: true,
      serverParams: {
        sort: { field: "days_inactive", type: "desc" },
        page: 1,
        perPage: 10
      },
      limit: "10",
      search: "",
      totalRows: 0,
      clients: [],
      rows: [
        {
          total_sales: 'Total',
          children: [],
        },
      ],

      // new: period filter
      period: 30,
      periodOptions: [
        { value: 30, text: this.$t('Last_30_days') },
        { value: 60, text: this.$t('Last_60_days') },
        { value: 90, text: this.$t('Last_90_days') },
      ],
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    columns() {
      return [
        {
          label: this.$t("CustomerName"),
          field: "name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: true
        },
        {
          label: this.$t("Phone"),
          field: "phone",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: true
        },
        {
          label: this.$t("TotalSales"),
          field: "total_sales",
          type: "number",
          headerField: this.sumCountSales, // sum total sales in group header
          tdClass: "text-left",
          thClass: "text-left",
          sortable: true
        },
        {
          label: this.$t("LastPurchase"),
          field: "last_sale_at",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: true
        },
        {
          label: this.$t("DaysInactive"),
          field: "days_inactive",
          type: "number",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: true
        },
        {
          label: this.$t("Action"),
          field: "actions",
          html: true,
          tdClass: "text-right",
          thClass: "text-right",
          sortable: false
        }
      ];
    }
  },

  methods: {
    // --- header aggregator for total_sales
    sumCountSales(rowObj) {
      let sum = 0;
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) return sum;
      for (let i = 0; i < rowObj.children.length; i++) {
        const v = rowObj.children[i].total_sales;
        if (typeof v === 'number') sum += v;
      }
      return sum;
    },

    //--------------------------- Download_PDF (reuse your endpoint) -------------------------------\\
    Download_PDF(client , id) {
      NProgress.start();
      NProgress.set(0.1);

      axios
        .get("report/client_pdf/" + id, {
          responseType: "blob",
          headers: { "Content-Type": "application/json" }
        })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute("download", "report-" + client.name + ".pdf");
          document.body.appendChild(link);
          link.click();
          setTimeout(() => NProgress.done(), 500);
        })
        .catch(() => setTimeout(() => NProgress.done(), 500));
    },

    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Inactive_Customers_Report(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Inactive_Customers_Report(1);
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
      this.Get_Inactive_Customers_Report(this.serverParams.page);
    },

    //---- Event on Search
    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Inactive_Customers_Report(this.serverParams.page);
    },

    //--------------------------- Fetch Inactive Customers -------------------------------\\
    Get_Inactive_Customers_Report(page) {
      NProgress.start();
      NProgress.set(0.1);

      axios
        .get(
          "report/inactive_customers" + // ⬅️ adjust if your route differs
            "?page=" + page +
            "&SortField=" + this.serverParams.sort.field +
            "&SortType=" + this.serverParams.sort.type +
            "&search=" + encodeURIComponent(this.search || "") +
            "&limit=" + this.limit +
            "&period=" + this.period
        )
        .then(response => {
          this.clients = response.data.report;
          this.totalRows = response.data.totalRows;
          this.rows[0].children = this.clients;
          NProgress.done();
          this.isLoading = false;
        })
        .catch(() => {
          NProgress.done();
          setTimeout(() => { this.isLoading = false; }, 500);
        });
    },

    onPeriodChange() {
      this.updateParams({ page: 1 });
      this.Get_Inactive_Customers_Report(1);
    },

    //------------------------------ Helpers -------------------------\\
    formatNumber(number, dec) {
      const value = (typeof number === "string" ? number : number.toString()).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec) return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}.${formated}`;
    },
  },

  created() {
    this.Get_Inactive_Customers_Report(1);
  }
};
</script>
