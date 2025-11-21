<template>
  <div class="main-content p-2 p-md-4">
    <breadcumb :page="$t('Stock_Adjustment_Report')" :folder="$t('Reports')" />

    <!-- Toolbar -->
    <b-card class="toolbar-card shadow-soft mb-3 border-0">
      <div class="d-flex flex-wrap align-items-center">

        <!-- Date range -->
        <div class="mr-3 mb-2">
          <label class="mb-1 d-block text-muted">{{$t('DateRange')}}</label>
          <date-range-picker
            v-model="dateRange"
            :locale-data="locale"
            :autoApply="true"
            :showDropdowns="true"
            :opens="isMobile ? 'center' : 'right'"
            :drops="'down'"
            :parentEl="parentEl"
            :linkedCalendars="false"
            @update="fetchReport"
          >
            <template v-slot:input="picker">
              <b-button variant="light" class="btn-pill">
                <i class="i-Calendar-4 mr-1"></i>
                {{ fmt(picker.startDate) }} - {{ fmt(picker.endDate) }}
              </b-button>
            </template>
          </date-range-picker>
        </div>

        <!-- Warehouse -->
        <div class="mr-3 mb-2">
          <label class="mb-1 d-block text-muted">{{$t('warehouse')}}</label>
          <v-select class="w-250"
            v-model="warehouse_id"
            @input="fetchReport"
            :reduce="o => o.value"
            :options="warehouses.map(w => ({label: w.name, value: w.id}))"
            :clearable="true"
            :placeholder="$t('Choose_Warehouse')"
          />
        </div>

        <div class="ml-auto mb-2 d-flex">
          <b-button variant="primary" class="btn-pill mr-2" @click="fetchReport">
            <i class="i-Reload mr-1"></i> {{$t('Refresh')}}
          </b-button>
          <b-button variant="danger" class="btn-pill" @click="exportPDF">
            <i class="i-File-PDF mr-1"></i> {{$t('Export_PDF')}}
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

    <!-- KPIs + Charts + Table -->
    <div v-else>
      <!-- KPIs -->
      <b-row>
        <b-col md="3" sm="6" class="mb-3">
          <StatTile icon="i-Edit" :label="$t('Adjustments')" :value="num(kpis.adjustments_count)" theme="blue" />
        </b-col>
        <b-col md="3" sm="6" class="mb-3">
          <StatTile icon="i-Add" :label="$t('QtyAdded')" :value="formatQty(kpis.qty_added)" theme="green" />
        </b-col>
        <b-col md="3" sm="6" class="mb-3">
          <StatTile icon="i-Remove" :label="$t('QtyRemoved')" :value="formatQty(kpis.qty_removed)" theme="red" />
        </b-col>
        <b-col md="3" sm="6" class="mb-3">
          <StatTile icon="i-Arrow-Repeat" :label="$t('NetQty')" :value="formatQty(kpis.net_qty)" theme="purple" />
        </b-col>
      </b-row>

      <!-- Charts -->
      <b-row>
        <b-col md="8" class="mb-3">
          <b-card class="shadow-soft border-0">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h6 class="m-0">{{$t('NetAdjustmentsOverTime')}}</h6>
              <small class="text-muted">{{ fmt(dateRange.startDate) }} - {{ fmt(dateRange.endDate) }}</small>
            </div>
            <v-chart :options="chartTimeOptions" autoresize style="height:300px;" />
          </b-card>
        </b-col>
        <b-col md="4" class="mb-3">
          <b-card class="shadow-soft border-0">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h6 class="m-0">{{$t('AdjustmentTypes')}}</h6>
              <small class="text-muted">{{$t('ByQuantity')}}</small>
            </div>
            <v-chart :options="chartTypeOptions" autoresize style="height:300px;" />
          </b-card>
        </b-col>
      </b-row>

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
            <span v-if="p.column.field === 'qty' || p.column.field === 'net_qty'">
              {{ formatQty(p.row[p.column.field]) }}
            </span>
            <span v-else>
              {{ p.formattedRow[p.column.field] }}
            </span>
          </template>
        </vue-good-table>
      </b-card>
    </div>
  </div>
</template>

<script>
import NProgress from "nprogress";
import { mapGetters } from "vuex";
import DateRangePicker from "vue2-daterange-picker";
import "vue2-daterange-picker/dist/vue2-daterange-picker.css";
import moment from "moment";

/* ECharts v4 style (Vue 2 + vue-echarts/components/ECharts.vue) */
import ECharts from "vue-echarts/components/ECharts.vue";
import "echarts/lib/echarts";
import "echarts/lib/chart/bar";
import "echarts/lib/chart/line";
import "echarts/lib/chart/pie";
import "echarts/lib/component/tooltip";
import "echarts/lib/component/legend";
import "echarts/lib/component/title";
import "echarts/lib/component/grid";

/* PDF export */
import jsPDF from "jspdf";
import "jspdf-autotable";

const StatTile = {
  name: "StatTile",
  functional: true,
  props: { icon:String, label:String, value:[String,Number], theme:{type:String,default:'blue'} },
  render(h,{props}){
    return h("div",{class:["stat-card",`theme-${props.theme}`,"shadow-soft","rounded-xl","mb-2"]},[
      h("div",{class:"stat-inner"},[
        h("div",{class:"stat-icon"},[h("i",{class:props.icon})]),
        h("div",{class:"stat-content"},[
          h("div",{class:"stat-label"},props.label),
          h("div",{class:"stat-value"},props.value)
        ])
      ])
    ]);
  }
};

export default {
  metaInfo: { title: "Stock Adjustment Report" },
  components: {
    "v-chart": ECharts,
    "date-range-picker": DateRangePicker,
    StatTile
  },
  data(){
    const end = new Date();
    const start = new Date(); start.setDate(end.getDate() - 6);
    return {
      warehouses: [],
      warehouse_id: null,

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

      isMobile: false,
      parentEl: "body",

      isLoading: true,
      kpis: { adjustments_count:0, qty_added:0, qty_removed:0, net_qty:0 },
      timeseries: [],
      byType: [],

      rows: [],
      totalRows: 0,

      serverParams: { page:1, perPage:10, sort:{ field:"dt", type:"desc" } },
      limit: 10,
      search: ""
    };
  },
  computed:{
    ...mapGetters(["currentUser"]),
    currency(){ return (this.currentUser && this.currentUser.currency) || "USD"; },

    columns(){
      return [
        { label:this.$t('ID'),         field:'adj_id',    sortable:true },
        { label:this.$t('Ref'),        field:'ref',       sortable:true },
        { label:this.$t('date'),       field:'date',      sortable:true },
        { label:this.$t('warehouse'),  field:'warehouse', sortable:true },
        { label:this.$t('Qty'),        field:'qty',       type:'number', sortable:true },
        { label:this.$t('NetQty'),     field:'net_qty',   type:'number', sortable:true },
      ];
    },

    chartTimeOptions(){
      const dates = this.timeseries.map(x => x.d);
      const net   = this.timeseries.map(x => Number(x.net_qty || 0));
      return {
        title: { show:false },
        tooltip: { trigger:'axis' },
        legend: { show:false },
        grid: { left: 10, right: 10, bottom: 10, top: 20, containLabel: true },
        xAxis: [{ type:'category', data: dates, axisTick:{show:false} }],
        yAxis: [{ type:'value' }],
        series: [
          { name:this.$t('NetQty'), type:'bar', data: net }
        ]
      };
    },

    chartTypeOptions(){
      const data = this.byType.map(x => ({
        name: x.type === 'add' ? this.$t('QtyAdded') :
              x.type === 'sub' ? this.$t('QtyRemoved') : x.type,
        value: Number(x.qty || 0)
      }));
      return {
        tooltip: { trigger:'item', formatter: ({name,value,percent}) => `${name}<br/>${this.formatQty(value)} (${percent}%)` },
        legend: { bottom: 0 },
        series: [{
          type:'pie',
          radius:'65%',
          center:['50%','45%'],
          data,
          label: { formatter: '{b}\n{d}%' }
        }]
      };
    }
  },
  methods:{
    // --- responsive helpers
    onResize(){ this.isMobile = window.innerWidth < 768; },

    // ---- formatters
    fmt(d){ return moment(d).format("YYYY-MM-DD"); },
    num(v){ const n = Number(v||0); return isNaN(n) ? "0" : n.toLocaleString(); },
    formatQty(v){ const n = Number(v||0); return isNaN(n) ? "0" : n.toLocaleString(undefined,{maximumFractionDigits:2}); },
    shortNumber(v){ return new Intl.NumberFormat(undefined,{notation:'compact',maximumFractionDigits:1}).format(Number(v||0)); },
    getWarehouseName(){
      if (!this.warehouse_id) return this.$t("All");
      const w = (this.warehouses || []).find(x => Number(x.id) === Number(this.warehouse_id));
      return w ? w.name : `#${this.warehouse_id}`;
    },

    // ---- table events
    onPageChange({ currentPage }) { this.serverParams.page = currentPage; this.fetchReport(); },
    onPerPageChange({ currentPerPage }) { this.serverParams.perPage = currentPerPage; this.limit = currentPerPage; this.serverParams.page = 1; this.fetchReport(); },
    onSortChange(params){ if (params && params[0]) this.serverParams.sort = params[0]; this.fetchReport(); },
    onSearch(v){ this.search = v.searchTerm || ""; this.fetchReport(); },

    // ---- data load
    fetchReport(){
      NProgress.start(); NProgress.set(0.1);
      this.isLoading = true;

      const qs = new URLSearchParams({
        from: this.fmt(this.dateRange.startDate),
        to:   this.fmt(this.dateRange.endDate),
        warehouse_id: this.warehouse_id || "",
        page: String(this.serverParams.page),
        limit: String(this.serverParams.perPage || this.limit),
        SortField: this.serverParams.sort?.field || "dt",
        SortType: this.serverParams.sort?.type || "desc",
        search: this.search || ""
      }).toString();

      axios.get(`report/stock_adjustment?${qs}`)
        .then(({data})=>{
          const d = data.data || {};
          this.kpis = d.kpis || this.kpis;
          this.timeseries = d.timeseries || [];
          this.byType = d.byType || [];
          this.rows = d.rows || [];
          this.totalRows = d.totalRows || 0;
          this.warehouses = data.warehouses || this.warehouses;
          this.isLoading = false; NProgress.done();
        })
        .catch(()=>{ this.isLoading = false; NProgress.done(); });
    },

    // Use one TTF for both weights
    useVazirmatn(pdf){
      try {
        // serve this from /public/fonts/Vazirmatn-Bold.ttf
        pdf.addFont('/fonts/Vazirmatn-Bold.ttf', 'Vazirmatn', 'normal');
        pdf.addFont('/fonts/Vazirmatn-Bold.ttf', 'Vazirmatn', 'bold');
      } catch(e) {
        // ignore if already added
      }
      pdf.setFont('Vazirmatn', 'normal'); // everything will render bold-ish, but Arabic will show correctly
    },

    isRTL(){
      return (this.$i18n && ['ar','fa','ur','he'].includes(this.$i18n.locale)) ||
            document.documentElement.dir === 'rtl';
    },

    async exportPDF(){
      // ... your fetch-all code (unchanged)
      const pdf = new jsPDF({ orientation:'portrait', unit:'pt', format:'a4' });
      this.useVazirmatn(pdf);
      const rtl = this.isRTL();
      const margin = 40, pageW = pdf.internal.pageSize.getWidth();

      // Header
      pdf.setFont('Vazirmatn','bold'); pdf.setFontSize(16);
      const title = this.$t('Stock_Adjustment_Report');
      rtl ? pdf.text(title, pageW - margin, 46, {align:'right'}) : pdf.text(title, margin, 46);
      pdf.setDrawColor(220); pdf.line(margin, 56, pageW - margin, 56);

      // Meta
      pdf.setFont('Vazirmatn','normal'); pdf.setFontSize(10);
      const meta = `${this.$t('DateRange')}: ${this.fmt(this.dateRange.startDate)} ${this.$t('to')||'to'} ${this.fmt(this.dateRange.endDate)}   •   ${this.$t('warehouse')}: ${this.getWarehouseName()}`;
      rtl ? pdf.text(meta, pageW - margin, 72, {align:'right'}) : pdf.text(meta, margin, 72);

      // Table
      const head = [[
        this.$t('ID'), this.$t('Ref'), this.$t('date'),
        this.$t('warehouse'), this.$t('Qty'), this.$t('NetQty'),
      ]];
      const items = (await this.fetchAllRowsForExport()).rows || [];
      const body = items.map(r => [
        String(r.adj_id ?? ''), String(r.ref ?? ''), String(r.date ?? ''),
        String(r.warehouse ?? ''), this.formatQty(r.qty), this.formatQty(r.net_qty),
      ]);
      const tQty = items.reduce((a,b)=>a+Number(b.qty||0),0);
      const tNet = items.reduce((a,b)=>a+Number(b.net_qty||0),0);

      pdf.autoTable({
        startY: 90,
        head, body,
        margin: { left: margin, right: margin },
        theme: 'striped',
        styles: {
          font: 'Vazirmatn',
          fontStyle: 'normal',
          fontSize: 9,
          cellPadding: 4,
          overflow: 'linebreak',
          halign: rtl ? 'right' : 'left',
        },
        headStyles: {
          font: 'Vazirmatn',
          fontStyle: 'bold',
          fillColor: [245,245,245],
          textColor: 20,
          halign: rtl ? 'right' : 'left',
        },
        columnStyles: rtl
          ? { 0:{halign:'right'},1:{halign:'right'},2:{halign:'right'},3:{halign:'right'},4:{halign:'right'},5:{halign:'right'} }
          : { 4:{halign:'right'}, 5:{halign:'right'} },
        foot: [[
          { content: this.$t('Totals'), styles:{ fontStyle:'bold' } },
          '', '', '',
          { content: this.formatQty(tQty), styles:{ halign:'right', fontStyle:'bold' } },
          { content: this.formatQty(tNet), styles:{ halign:'right', fontStyle:'bold' } },
        ]],
        didDrawPage: d => {
          const pw = pdf.internal.pageSize.getWidth();
          const ph = pdf.internal.pageSize.getHeight();
          pdf.setFont('Vazirmatn','normal'); pdf.setFontSize(8);
          pdf.text(`${d.pageNumber} / ${pdf.internal.getNumberOfPages()}`, pw - margin, ph - 14, { align:'right' });
        }
      });

      pdf.save(`Stock_Adjustments_${this.fmt(this.dateRange.startDate)}_${this.fmt(this.dateRange.endDate)}.pdf`);
    },



    // Helper: fetch all pages for PDF
    async fetchAllRowsForExport() {
      const perPage = 500;
      let page = 1;
      let totalRows = Infinity;
      const allRows = [];

      while (allRows.length < totalRows) {
        const qs = new URLSearchParams({
          from: this.fmt(this.dateRange.startDate),
          to:   this.fmt(this.dateRange.endDate),
          warehouse_id: this.warehouse_id || "",
          page: String(page),
          limit: String(perPage),
          SortField: this.serverParams.sort?.field || "dt",
          SortType: this.serverParams.sort?.type || "desc",
          search: this.search || ""
        }).toString();

        const { data } = await axios.get(`report/stock_adjustment?${qs}`);
        const d = data?.data || {};
        const rows = d.rows || [];
        totalRows = Number(d.totalRows || rows.length || 0);

        allRows.push(...rows);
        if (rows.length < perPage) break;
        page += 1;
      }
      return { rows: allRows };
    }
  },
  created(){
    this.fetchReport();
  },
  mounted(){
    this.onResize();
    window.addEventListener('resize', this.onResize, { passive: true });
  },
  beforeDestroy(){
    window.removeEventListener('resize', this.onResize);
  }
};
</script>

<style scoped>
.rounded-xl{border-radius:1rem;}
.shadow-soft{box-shadow:0 12px 24px rgba(0,0,0,.06), 0 2px 6px rgba(0,0,0,.05);}
.toolbar-card{background:#fff;}
.btn-pill{border-radius:999px;}
.w-250{width:250px;}

.stat-card{padding:14px 16px;min-height:100px;display:flex;align-items:center;background:#fff;}
.stat-inner{display:flex;align-items:center;}
.stat-icon{width:48px;height:48px;border-radius:12px;background:#f8f9fa;display:flex;align-items:center;justify-content:center;margin-right:12px;}
.stat-icon i{font-size:22px;}
.stat-label{font-size:.85rem;font-weight:600;color:#666;}
.stat-value{font-size:1.3rem;font-weight:700;color:#333;}

.theme-blue .stat-icon{color:#0b5fff;}
.theme-green .stat-icon{color:#2e7d32;}
.theme-red .stat-icon{color:#c62828;}
.theme-purple .stat-icon{color:#6a1b9a;}
</style>

<!-- Global (not scoped) styles for the daterangepicker popup -->
<style>
/* Make sure the popup floats above cards/modals */
.daterangepicker { z-index: 2050 !important; }

/* Mobile: stretch & stack */
@media (max-width: 575.98px) {
  .daterangepicker {
    left: 12px !important;
    right: 12px !important;
    width: calc(100% - 24px) !important;
  }
  .daterangepicker .drp-calendar.left,
  .daterangepicker .drp-calendar.right {
    float: none !important;
    width: 100% !important;
  }
  .daterangepicker .drp-calendar.right {
    display: none; /* hide second calendar on small screens */
  }
  .daterangepicker .ranges {
    float: none !important;
    width: 100% !important;
    margin: 8px 0 0 0 !important;
  }
}
</style>
