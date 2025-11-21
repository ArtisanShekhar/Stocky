<template>
  <div class="main-content p-2 p-md-4">
    <breadcumb :page="$t('SalesInvoice')" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else>
      <!-- Toolbar -->
      <b-card class="shadow-soft border-0 mb-3">
        <div class="d-flex flex-wrap align-items-center">
          <!-- Date range (responsive) -->
          <div class="mr-3 mb-2 d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center">
            <label class="mb-1 mb-sm-0 mr-sm-2 text-muted">{{$t('DateRange')}}</label>
            <date-range-picker
              v-model="dateRange"
              :locale-data="locale"
              :autoApply="true"
              :showDropdowns="true"
              @update="Submit_filter_dateRange"
            >
              <template v-slot:input="picker">
                <b-button variant="light" class="btn-pill w-100 text-left">
                  <i class="i-Calendar-4 mr-1"></i>
                  {{ fmt(picker.startDate) }} — {{ fmt(picker.endDate) }}
                </b-button>
              </template>
            </date-range-picker>
          </div>

          <!-- Quick ranges -->
          <div class="mr-3 mb-2">
            <label class="mb-1 d-block text-muted">{{$t('QuickRanges')}}</label>
            <div class="btn-group">
              <b-button size="sm" variant="outline-primary" @click="quick('7d')">7D</b-button>
              <b-button size="sm" variant="outline-primary" @click="quick('30d')">30D</b-button>
              <b-button size="sm" variant="outline-primary" @click="quick('90d')">90D</b-button>
              <b-button size="sm" variant="outline-primary" @click="quick('mtd')">{{$t('MTD')}}</b-button>
              <b-button size="sm" variant="outline-primary" @click="quick('ytd')">{{$t('YTD')}}</b-button>
            </div>
          </div>

          <div class="ml-auto mb-2">
            <b-button variant="primary" class="btn-pill mr-2" @click="Payments_Sales(serverParams.page)">
              <i class="i-Reload mr-1"></i> {{$t('Refresh')}}
            </b-button>
            <b-button @click="Payment_PDF" size="sm" variant="outline-success" class="btn-pill mr-2">
              <i class="i-File-Copy"></i> PDF
            </b-button>
            <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger btn-pill"
              :data="payments"
              :columns="excelColumns"
              :file-name="'payments_sales'"
              :file-type="'xlsx'"
              :sheet-name="'payments_sales'"
            >
              <i class="i-File-Excel"></i> EXCEL
            </vue-excel-xlsx>
          </div>
        </div>
      </b-card>

      <!-- Table -->
      <b-card class="wrapper shadow-soft border-0">
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
          styleClass="table-hover tableOne vgt-table"
        >
          <div slot="table-actions" class="mt-2 mb-3">
            <b-button variant="outline-info ripple m-1" size="sm" v-b-toggle.sidebar-right class="btn-pill">
              <i class="i-Filter-2"></i> {{ $t('Filter') }}
            </b-button>
          </div>

          <template slot="table-row" slot-scope="props">
            <span v-if="props.column.field === 'montant'">
              {{ Number(props.row.montant || 0).toLocaleString(undefined,{maximumFractionDigits:2}) }}
            </span>
            <span v-else>
              {{ props.formattedRow[props.column.field] }}
            </span>
          </template>
        </vue-good-table>
      </b-card>
    </div>

    <!-- Sidebar Filter -->
    <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
      <div class="px-3 py-2">
        <b-row>
          <!-- Reference -->
          <b-col md="12">
            <b-form-group :label="$t('Reference')">
              <b-form-input :placeholder="$t('Reference')" v-model="Filter_Ref" />
            </b-form-group>
          </b-col>

          <!-- Customers  -->
          <b-col md="12">
            <b-form-group :label="$t('Customer')">
              <v-select
                :reduce="o => o.value"
                :placeholder="$t('Choose_Customer')"
                v-model="Filter_client"
                :options="clients.map(c => ({label: c.name, value: c.id}))"
                :clearable="true"
              />
            </b-form-group>
          </b-col>

          <!-- Sale  -->
          <b-col md="12">
            <b-form-group :label="$t('Sale')">
              <v-select
                :reduce="o => o.value"
                :placeholder="$t('PleaseSelect')"
                v-model="Filter_sale"
                :options="sales.map(s => ({label: s.Ref, value: s.id}))"
                :clearable="true"
              />
            </b-form-group>
          </b-col>

          <!-- Payment choice -->
          <b-col md="12">
            <b-form-group :label="$t('Paymentchoice')">
              <v-select
                v-model="Filter_Reg"
                :reduce="o => o.value"
                :placeholder="$t('PleaseSelect')"
                :options="payment_methods.map(pm => ({label: pm.name, value: pm.id}))"
                :clearable="true"
              />
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12">
            <b-button
              @click="Payments_Sales(1)"
              variant="primary"
              size="sm"
              block
              class="btn-pill"
            >
              <i class="i-Filter-2"></i> {{ $t('Filter') }}
            </b-button>
          </b-col>
          <b-col md="6" sm="12">
            <b-button @click="Reset_Filter" variant="danger" size="sm" block class="btn-pill">
              <i class="i-Power-2"></i> {{ $t('Reset') }}
            </b-button>
          </b-col>
        </b-row>
      </div>
    </b-sidebar>
  </div>
</template>

<script>
import NProgress from "nprogress";
import jsPDF from "jspdf";
import "jspdf-autotable";
import DateRangePicker from "vue2-daterange-picker";
import "vue2-daterange-picker/dist/vue2-daterange-picker.css";
import moment from "moment";

export default {
  metaInfo: { title: "Payment Sales" },
  components: { DateRangePicker },

  data() {
    const end = new Date();
    const start = new Date(); start.setDate(end.getDate() - 29);
    return {
      isLoading: true,

      // table state
      serverParams: { sort: { field: "id", type: "desc" }, page: 1, perPage: 10 },
      limit: "10",
      search: "",
      totalRows: 0,

      // filters
      Filter_client: "",
      Filter_Ref: "",
      Filter_sale: "",
      Filter_Reg: "",

      // data
      payments: [],
      clients: [],
      sales: [],
      payment_methods: [],

      // vgt rows (with footer group)
      rows: [{ children: [] }],

      // date range
      dateRange: { startDate: start, endDate: end },
      locale: {
        Label: this.$t("Apply") || "Apply",
        cancelLabel: this.$t("Cancel") || "Cancel",
        weekLabel: "W",
        customRangeLabel: this.$t("CustomRange") || "Custom Range",
        daysOfWeek: moment.weekdaysMin(),
        monthNames: moment.monthsShort(),
        firstDay: 1
      }
    };
  },

  computed: {
    columns() {
      return [
        { label: this.$t("date"),          field: "date",           tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Reference"),     field: "Ref",            tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Sale"),          field: "Ref_Sale",       tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Customer"),      field: "client_name",    tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("ModePaiement"),  field: "payment_method", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Account"),       field: "account_name",   tdClass: "text-left", thClass: "text-left", sortable:false },
        { label: this.$t("Amount"),        field: "montant",        type: "decimal", headerField: this.sumCount, tdClass: "text-left", thClass: "text-left" },
      ];
    },

    // for excel lib (simple mapping)
    excelColumns(){
      return [
        { label: this.$t("date"), field: "date" },
        { label: this.$t("Reference"), field: "Ref" },
        { label: this.$t("Sale"), field: "Ref_Sale" },
        { label: this.$t("Customer"), field: "client_name" },
        { label: this.$t("ModePaiement"), field: "payment_method" },
        { label: this.$t("Account"), field: "account_name" },
        { label: this.$t("Amount"), field: "montant" },
      ];
    }
  },

  methods: {
    // ---------- utils ----------
    fmt(d){ return moment(d).format("YYYY-MM-DD"); },
    sumCount(rowObj){
      let sum = 0;
      if (rowObj && Array.isArray(rowObj.children)) {
        for (const r of rowObj.children) sum += Number(r.montant || 0);
      }
      return sum;
    },
    findLabel(list, id, key='name'){
      if (!id) return this.$t('All');
      const x = (list||[]).find(i => String(i.id) === String(id));
      return x ? (x[key] ?? this.$t('All')) : this.$t('All');
    },
    findSaleRef(id){
      if (!id) return this.$t('All');
      const x = (this.sales||[]).find(i => String(i.id) === String(id));
      return x ? (x.Ref || this.$t('All')) : this.$t('All');
    },

    // ---------- quick ranges ----------
    quick(kind){
      const now = moment(); let s, e = now.clone();
      if (kind==='7d')  s = now.clone().subtract(6,'days');
      if (kind==='30d') s = now.clone().subtract(29,'days');
      if (kind==='90d') s = now.clone().subtract(89,'days');
      if (kind==='mtd'){ s = now.clone().startOf('month'); e = now; }
      if (kind==='ytd'){ s = now.clone().startOf('year');  e = now; }
      this.dateRange = { startDate: s.toDate(), endDate: e.toDate() };
      this.Payments_Sales(1);
    },

    // ---------- table handlers ----------
    updateParams(newProps){ this.serverParams = Object.assign({}, this.serverParams, newProps); },
    onPageChange({ currentPage }){ if (this.serverParams.page !== currentPage){ this.updateParams({ page: currentPage }); this.Payments_Sales(currentPage); } },
    onPerPageChange({ currentPerPage }){ if (this.limit !== currentPerPage){ this.limit = String(currentPerPage); this.updateParams({ page: 1, perPage: currentPerPage }); this.Payments_Sales(1); } },
    onSortChange(params){
      if (params && params[0]) {
        const field = params[0].field === 'Ref_Sale' ? 'sale_id' : params[0].field;
        this.updateParams({ sort: { type: params[0].type, field } });
        this.Payments_Sales(this.serverParams.page);
      }
    },
    onSearch(value){ this.search = value.searchTerm || ""; this.Payments_Sales(this.serverParams.page); },

    // ---------- date picker ----------
    Submit_filter_dateRange(){
      // fetch with new range
      this.Payments_Sales(1);
    },

    // ---------- filters ----------
    Reset_Filter(){
      this.search = "";
      this.Filter_client = "";
      this.Filter_Ref = "";
      this.Filter_sale = "";
      this.Filter_Reg = "";
      this.Payments_Sales(1);
    },

    // ---------- fetch ----------
    Payments_Sales(page){
      NProgress.start(); NProgress.set(0.1);

      // Normalize null -> ''
      const client_id  = this.Filter_client  || '';
      const sale_id    = this.Filter_sale    || '';
      const method_id  = this.Filter_Reg     || '';
      const ref        = this.Filter_Ref     || '';
      const from       = this.fmt(this.dateRange.startDate);
      const to         = this.fmt(this.dateRange.endDate);

      const url = "payment_sale?" + new URLSearchParams({
        page: String(page),
        Ref: ref,
        client_id,
        sale_id,
        payment_method_id: method_id,
        SortField: this.serverParams.sort.field,
        SortType: this.serverParams.sort.type,
        search: this.search || '',
        limit: this.limit,
        to, from
      }).toString();

      axios.get(url)
        .then(({data})=>{
          this.payments = data.payments || [];
          this.clients = data.clients || [];
          this.sales = data.sales || [];
          this.payment_methods = data.payment_methods || [];
          this.totalRows = Number(data.totalRows || 0);
          this.rows[0].children = this.payments;
          NProgress.done();
          this.isLoading = false;
        })
        .catch(()=>{ NProgress.done(); setTimeout(()=>{ this.isLoading=false; }, 300); });
    },


    // ---------- shared font + RTL helpers ----------
    useVazirmatn(pdf){
      // Make sure this file exists and is publicly accessible:
      // /public/fonts/Vazirmatn-Bold.ttf
      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      try {
        // Reuse the same TTF as "normal" and "bold"
        pdf.addFont(fontPath, "Vazirmatn", "normal");
        pdf.addFont(fontPath, "Vazirmatn", "bold");
      } catch(e){ /* ignore if already added */ }
      pdf.setFont("Vazirmatn", "normal");
    },
    isRTL(){
      // works with vue-i18n or <html dir="rtl">
      return (this.$i18n && ['ar','fa','ur','he'].includes(this.$i18n.locale))
          || (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');
    },

    // ---------- lookups (safe if null) ----------
    findLabel(list, id, key='name'){
      if (!id) return this.$t('All');
      const x = (list||[]).find(i => String(i.id) === String(id));
      return x ? (x[key] ?? this.$t('All')) : this.$t('All');
    },
    findSaleRef(id){
      if (!id) return this.$t('All');
      const x = (this.sales||[]).find(i => String(i.id) === String(id));
      return x ? (x.Ref || this.$t('All')) : this.$t('All');
    },

    // ---------- EXPORT PDF (Payments Sales) ----------
    async Payment_PDF(){
      NProgress.start(); NProgress.set(0.2);
      try{
        // robust date formatting
        const fmtLocal = (d) => {
          if (!d) return '';
          if (this.fmt) return this.fmt(d);
          return (d instanceof Date) ? d.toISOString().slice(0,10) : String(d);
        };
        const from = this.startDate || fmtLocal(this.dateRange?.startDate);
        const to   = this.endDate   || fmtLocal(this.dateRange?.endDate);

        // fetch ALL rows using current filters/sort
        const qs = new URLSearchParams({
          page: '1',
          limit: '-1',
          SortField: this.serverParams?.sort?.field || 'id',
          SortType:  this.serverParams?.sort?.type  || 'desc',
          search: this.search || '',
          from, to,
          Ref: this.Filter_Ref || '',
          client_id: this.Filter_client || '',
          sale_id: this.Filter_sale || '',
          payment_method_id: this.Filter_Reg || ''
        }).toString();

        const { data } = await axios.get(`payment_sale?${qs}`).catch(()=>({data:{}}));
        const items = Array.isArray(data?.payments) ? data.payments : [];

        // PDF setup (landscape A4)
        const pdf = new jsPDF({ orientation:'landscape', unit:'pt', format:'a4' });
        this.useVazirmatn(pdf);
        const rtl = this.isRTL();
        const margin = 40;
        const pageW = pdf.internal.pageSize.getWidth();

        // Title
        pdf.setFont('Vazirmatn','bold'); pdf.setFontSize(16);
        const title = this.$t('SalesInvoice');
        rtl ? pdf.text(title, pageW - margin, 40, { align:'right' })
            : pdf.text(title, margin, 40);

        // Header (filters + date range) with auto-wrap
        pdf.setFont('Vazirmatn','normal'); pdf.setFontSize(10);
        const customerLabel = this.findLabel(this.clients, this.Filter_client, 'name');
        const saleLabel     = this.findSaleRef(this.Filter_sale);
        const methodLabel   = this.findLabel(this.payment_methods, this.Filter_Reg, 'name');
        const refFilter     = this.Filter_Ref || this.$t('All');
        const range         = `${from || '—'} — ${to || '—'}`;

        const headerText = [
          `${this.$t('DateRange')}: ${range}`,
          `${this.$t('Reference')}: ${refFilter}`,
          `${this.$t('Customer')}: ${customerLabel}`,
          `${this.$t('Sale')}: ${saleLabel}`,
          `${this.$t('ModePaiement')}: ${methodLabel}`
        ].join('   •   ');

        const wrapped = pdf.splitTextToSize(headerText, pageW - margin*2);
        rtl ? pdf.text(wrapped, pageW - margin, 58, { align:'right' })
            : pdf.text(wrapped, margin, 58);

        // Table
        const head = [[
          this.$t('date'),
          this.$t('Reference'),
          this.$t('Sale'),
          this.$t('Customer'),
          this.$t('ModePaiement'),
          this.$t('Account'),
          this.$t('Amount'),
        ]];

        const body = items.map(r => ([
          r.date || '',
          r.Ref || '',
          r.Ref_Sale || '',
          r.client_name || '',
          r.payment_method || '',
          r.account_name || '',
          Number(r.montant || 0).toFixed(2)
        ]));

        const total = items.reduce((a,b)=> a + Number(b.montant || 0), 0);

        pdf.autoTable({
          startY: 80,
          head, body,
          margin: { left: margin, right: margin },
          theme: 'striped',
          styles: {
            font: 'Vazirmatn',
            fontStyle: 'normal',
            fontSize: 9,
            cellPadding: 6,
            overflow: 'linebreak',
            halign: rtl ? 'right' : 'left',
          },
          headStyles: {
            font: 'Vazirmatn',
            fontStyle: 'bold',
            fillColor: [11,95,255],
            textColor: 255,
            halign: rtl ? 'right' : 'left',
          },
          columnStyles: {
            6: { halign:'right' }, // Amount column
          },
          foot: [[
            { content: this.$t('Totals'), colSpan: 6, styles:{ halign:'right', fontStyle:'bold' } },
            { content: total.toFixed(2),  styles:{ halign:'right', fontStyle:'bold' } }
          ]],
          didDrawPage: (d) => {
            pdf.setFont('Vazirmatn','normal'); pdf.setFontSize(8);
            pdf.text(`${d.pageNumber} / ${pdf.internal.getNumberOfPages()}`,
                    pageW - margin, pdf.internal.pageSize.getHeight() - 14, { align:'right' });
          }
        });

        pdf.save(`payments_sales_${from || 'all'}_${to || 'all'}.pdf`);
      } finally {
        NProgress.done();
      }
    },




  },

  created(){ this.Payments_Sales(1); }
};
</script>

<style scoped>
.shadow-soft{box-shadow:0 12px 24px rgba(0,0,0,.06),0 2px 6px rgba(0,0,0,.05);}
.btn-pill{border-radius:999px;}
</style>
