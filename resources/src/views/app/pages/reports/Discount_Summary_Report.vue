<template>
  <div class="main-content p-2 p-md-4">
    <breadcumb :page="$t('Discount_Summary_Report')" :folder="$t('Reports')" />

    <!-- Toolbar -->
    <b-card class="toolbar-card shadow-soft mb-3 border-0">
      <div class="d-flex flex-wrap align-items-center">
        <!-- Date range -->
        <div class="filter-block date-range-filter mr-3 mb-2">
          <label class="mb-1 d-block text-muted">{{ $t('DateRange') }}</label>

          <date-range-picker
            v-model="dateRange"
            :locale-data="locale"
            :autoApply="true"
            :showDropdowns="true"
            :opens="isMobile ? 'center' : 'right'"
            :drops="'down'"
            @update="fetchReport"
          >
            <template v-slot:input="picker">
              <b-button
                variant="light"
                class="btn-pill date-btn"
                :class="{ 'w-100': isMobile }"
              >
                <i class="i-Calendar-4 mr-1"></i>
                <!-- full text on ≥ sm -->
                <span class="d-none d-sm-inline">
                  {{ fmt(picker.startDate) }} — {{ fmt(picker.endDate) }}
                </span>
                <!-- compact on < sm -->
                <span class="d-inline d-sm-none">
                  {{ fmtShort(picker.startDate) }}–{{ fmtShort(picker.endDate) }}
                </span>
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
          <b-button variant="success" class="btn-pill mr-2" @click="exportPDF">
            <i class="i-File-PDF mr-1"></i> {{$t('Export_PDF')}}
          </b-button>
          <b-button variant="primary" class="btn-pill" @click="fetchReport">
            <i class="i-Reload mr-1"></i> {{$t('Refresh')}}
          </b-button>
        </div>
      </div>
    </b-card>

    <!-- Loading -->
    <div v-if="isLoading" class="mb-4">
      <b-row>
        <b-col md="4" v-for="n in 6" :key="'skel-'+n" class="mb-3">
          <b-skeleton-img class="rounded-xl shadow-soft" height="110px" />
        </b-col>
      </b-row>
    </div>

    <!-- Content -->
    <div v-else>
      <!-- ECharts -->
      <b-card class="shadow-soft border-0 mb-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="m-0">{{$t('DiscountsOverTime')}}</h6>
          <small class="text-muted">{{ fmt(dateRange.startDate) }} → {{ fmt(dateRange.endDate) }}</small>
        </div>
        <v-chart :options="chartOptions" autoresize style="height:300px;" />
      </b-card>

      <!-- Table -->
      <b-card class="shadow-soft border-0">
        <vue-good-table
          mode="remote"
          :rows="rows"
          :columns="columns"
          :totalRows="totalRows"
          styleClass="tableOne table-hover vgt-table"
          :pagination-options="{enabled:true, mode:'records'}"
          :search-options="{enabled:true, placeholder:$t('Search_this_table')}"
          @on-page-change="onPageChange"
          @on-per-page-change="onPerPageChange"
          @on-sort-change="onSortChange"
          @on-search="onSearch"
        >
          <template slot="table-row" slot-scope="p">
            <span v-if="['line_discount','header_discount','total_discount'].includes(p.column.field)">
              {{ money(p.row[p.column.field]) }}
            </span>
            <span v-else>{{ p.formattedRow[p.column.field] }}</span>
          </template>

          <!-- Footer grand total -->
          <template slot="table-actions-bottom">
            <div class="d-flex justify-content-end w-100 pt-2">
              <div class="font-weight-bold">
                {{$t('Total')}}: <span>{{ money(overallTotal) }}</span>
              </div>
            </div>
          </template>
        </vue-good-table>
      </b-card>
    </div>
  </div>
</template>

<script>
import NProgress from "nprogress";
import moment from "moment";
import { mapGetters } from "vuex";
import DateRangePicker from "vue2-daterange-picker";
import "vue2-daterange-picker/dist/vue2-daterange-picker.css";

/**
 * ECharts (vue-echarts v4 style)
 * Keep these side-effect imports so the series/components are registered.
 */
import ECharts from "vue-echarts/components/ECharts.vue";
import "echarts/lib/echarts";
import "echarts/lib/chart/line";
import "echarts/lib/chart/bar";
import "echarts/lib/chart/pie";
import "echarts/lib/component/tooltip";
import "echarts/lib/component/legend";
import "echarts/lib/component/grid";

import jsPDF from "jspdf";
import "jspdf-autotable";

export default {
  metaInfo: { title: "Discount Summary Report" },
  components: { "date-range-picker": DateRangePicker, "v-chart": ECharts },

  data() {
    const end = new Date(); const start = new Date(); start.setDate(end.getDate() - 29);
    return {
      isLoading: true,
      isMobile: false,
      // date filters
      dateRange: { startDate: start, endDate: end },
      locale: {
        Label: this.$t("Apply") || "Apply",
        cancelLabel: this.$t("Cancel") || "Cancel",
        weekLabel: "W",
        customRangeLabel: this.$t("CustomRange") || "Custom Range",
        daysOfWeek: moment.weekdaysMin(),
        monthNames: moment.monthsShort(),
        firstDay: 1
      },

      // table state
      serverParams: { page: 1, perPage: 10, sort: { field: "date_time", type: "desc" } },
      limit: 10,
      search: "",
      totalRows: 0,
      rows: [],
      overallTotal: 0,

      // chart source
      timeseries: [],
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    currency(){ return (this.currentUser && this.currentUser.currency) || "USD"; },

    columns() {
      return [
        { label: this.$t('ID'),             field:'sale_id',        sortable:true },
        { label: this.$t('date'),           field:'date_time',      sortable:true },
        { label: this.$t('User'),           field:'user_name',      sortable:true, tdClass:'text-left', thClass:'text-left' },
        { label: this.$t('Line_Discount'),  field:'line_discount',  type:'number', sortable:true },
        { label: this.$t('Header_Discount'),field:'header_discount',type:'number', sortable:true },
        { label: this.$t('Total_Discount'), field:'total_discount', type:'number', sortable:true },
      ];
    },

    // ECharts options built from timeseries
    chartOptions(){
      const dates = this.timeseries.map(x => x.d);
      const vals  = this.timeseries.map(x => Number(x.total_discount || 0));
      const vm = this;

      return {
        tooltip: {
          trigger: 'axis',
          formatter(params){
            const p = Array.isArray(params) ? params[0] : params;
            const val = Number(p.value || 0);
            const money = (() => {
              try { return new Intl.NumberFormat(undefined, { style:'currency', currency: vm.currency }).format(val); }
              catch { return `${vm.currency} ${val.toLocaleString()}`; }
            })();
            return `${p.axisValue}<br/>${p.marker} ${vm.$t('Total_Discount')}: <b>${money}</b>`;
          }
        },
        legend: { data: [vm.$t('Total_Discount')] },
        grid: { left: 10, right: 10, bottom: 10, top: 40, containLabel: true },
        xAxis: [{ type: 'category', data: dates, axisTick: { show: false } }],
        yAxis: [{
          type: 'value',
          axisLabel: {
            formatter(value){
              try { return new Intl.NumberFormat(undefined,{notation:'compact',maximumFractionDigits:1}).format(Number(value||0)); }
              catch { return value; }
            }
          }
        }],
        series: [
          { name: vm.$t('Total_Discount'), type: 'line', smooth: true, data: vals }
        ]
      };
    }
  },

  methods: {
    fmt(d){ return moment(d).format('YYYY-MM-DD'); },
    fmtShort(d){ return moment(d).format('MMM D'); },
    handleResize() { this.isMobile = window.innerWidth < 576; },
    money(v){
      try { return new Intl.NumberFormat(undefined,{style:'currency',currency:this.currency}).format(Number(v||0)); }
      catch { return `${this.currency} ${(Number(v||0)).toLocaleString()}`; }
    },

    quick(kind){
      const now = moment(); let s, e = now.clone();
      if(kind==='7d')  s = now.clone().subtract(6,'days');
      if(kind==='30d') s = now.clone().subtract(29,'days');
      if(kind==='90d') s = now.clone().subtract(89,'days');
      if(kind==='mtd') s = now.clone().startOf('month');
      if(kind==='ytd') s = now.clone().startOf('year');
      this.dateRange = { startDate: s.toDate(), endDate: e.toDate() };
      this.fetchReport();
    },

    // table events
    onPageChange({ currentPage }) { this.serverParams.page = currentPage; this.fetchReport(); },
    onPerPageChange({ currentPerPage }) { this.serverParams.perPage = currentPerPage; this.limit = currentPerPage; this.serverParams.page = 1; this.fetchReport(); },
    onSortChange(params){ if (params && params[0]) this.serverParams.sort = params[0]; this.fetchReport(); },
    onSearch(v){ this.search = v.searchTerm || ''; this.fetchReport(); },

    // Export PDF (RTL + Vazirmatn + per-column totals)
    exportPDF() {
      // collect items (supports either plain array or [{children:[]}] shape)
      const items =
        Array.isArray(this.rows?.[0]?.children) && this.rows.length === 1
          ? this.rows[0].children
          : (this.rows || []);

      // totals
      const tLine   = items.reduce((a,b)=> a + Number(b.line_discount   || 0), 0);
      const tHeader = items.reduce((a,b)=> a + Number(b.header_discount || 0), 0);
      const tTotal  = items.reduce((a,b)=> a + Number(b.total_discount  || 0), 0);

      const doc = new jsPDF({ orientation:'portrait', unit:'pt', format:'a4' });
      const pageW = doc.internal.pageSize.getWidth();
      const marginX = 40;

      // Font: use your single Vazirmatn-Bold for normal + bold
      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      try {
        doc.addFont(fontPath, "Vazirmatn", "normal");
        doc.addFont(fontPath, "Vazirmatn", "bold");
      } catch(_) { /* ignore if already added */ }
      doc.setFont("Vazirmatn", "normal");

      // RTL detection
      const rtl =
        (this.$i18n && ['ar','fa','ur','he'].includes(this.$i18n.locale)) ||
        (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');

      const title = this.$t('Discount_Summary_Report');
      const range = `${this.fmt(this.dateRange.startDate)} — ${this.fmt(this.dateRange.endDate)}`;

      // Header
      doc.setFont("Vazirmatn", "bold"); doc.setFontSize(14);
      rtl ? doc.text(title, pageW - marginX, 40, { align:'right' })
          : doc.text(title, marginX, 40);
      doc.setFont("Vazirmatn", "normal"); doc.setFontSize(10);
      rtl ? doc.text(range, pageW - marginX, 58, { align:'right' })
          : doc.text(range, marginX, 58);

      // Table
      const head = [[
        this.$t('ID'),
        this.$t('date'),
        this.$t('User'),
        this.$t('Line_Discount'),
        this.$t('Header_Discount'),
        this.$t('Total_Discount')
      ]];

      const body = items.map(r => ([
        r.sale_id ?? '',
        r.date_time ?? '',
        r.user_name ?? '',
        Number(r.line_discount   || 0).toFixed(2),
        Number(r.header_discount || 0).toFixed(2),
        Number(r.total_discount  || 0).toFixed(2),
      ]));

      doc.autoTable({
        startY: 80,
        head, body,
        styles: {
          font: "Vazirmatn",
          fontSize: 9,
          cellPadding: 6,
          halign: rtl ? 'right' : 'left'
        },
        headStyles: {
          font: "Vazirmatn",
          fontStyle: "bold",
          fillColor: [11,95,255],
          textColor: 255,
          halign: rtl ? 'right' : 'left'
        },
        columnStyles: {
          3: { halign: 'right' }, // Line_Discount
          4: { halign: 'right' }, // Header_Discount
          5: { halign: 'right' }, // Total_Discount
        },
        foot: [[
          { content: this.$t('Totals'), styles:{ font: 'Vazirmatn', fontStyle:'bold', halign: rtl ? 'right' : 'left' } },
          '', '',
          { content: tLine.toFixed(2),   styles:{ halign:'right', fontStyle:'bold' } },
          { content: tHeader.toFixed(2), styles:{ halign:'right', fontStyle:'bold' } },
          { content: tTotal.toFixed(2),  styles:{ halign:'right', fontStyle:'bold' } },
        ]],
        margin: { left: marginX, right: marginX }
      });

      doc.save(`discount-sales_${this.fmt(this.dateRange.startDate)}_${this.fmt(this.dateRange.endDate)}.pdf`);
    },



    // data load
    fetchReport(){
      NProgress.start(); NProgress.set(0.1); this.isLoading = true;

      const qs = new URLSearchParams({
        from: this.fmt(this.dateRange.startDate),
        to:   this.fmt(this.dateRange.endDate),
        page: String(this.serverParams.page),
        limit: String(this.serverParams.perPage || this.limit),
        SortField: this.serverParams.sort?.field || 'date_time',
        SortType:  this.serverParams.sort?.type || 'desc',
        search: this.search || ''
      }).toString();

      axios.get(`report/discount_summary?${qs}`)
        .then(({data})=>{
          this.rows = Array.isArray(data.report) ? data.report : [];
          this.totalRows = Number(data.totalRows || 0);
          this.overallTotal = Number(data.overall_total || 0);
          this.timeseries = Array.isArray(data.timeseries) ? data.timeseries : [];
          this.isLoading = false; NProgress.done();
        })
        .catch(()=>{ this.isLoading = false; NProgress.done(); });
    }
  },
  mounted() {
    this.handleResize();
    window.addEventListener('resize', this.handleResize);
  },
  beforeDestroy() {
    window.removeEventListener('resize', this.handleResize);
  },

  created(){ this.fetchReport(); }
};
</script>

<style scoped>
/* make the block stretch on phones */
.date-range-filter { min-width: 240px; }
@media (max-width: 575.98px) {
  .date-range-filter { width: 100%; }
  .date-btn { justify-content: center; }
  /* keep the popup inside the viewport on mobile */
  .daterangepicker {
    left: 0 !important;
    right: 0 !important;
    margin: 0 !important;
    width: 100vw !important;
    max-width: 100vw !important;
  }
  /* stack calendars/ranges vertically if needed */
  .daterangepicker .ranges,
  .daterangepicker .drp-calendar {
    float: none !important;
    width: 100% !important;
  }
}

.rounded-xl { border-radius:1rem; }
.shadow-soft { box-shadow:0 12px 24px rgba(0,0,0,.06), 0 2px 6px rgba(0,0,0,.05); }
.toolbar-card { background:#fff; }
.btn-pill { border-radius:999px; }
</style>
