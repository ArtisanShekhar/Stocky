<template>
  <div class="main-content import-products">
    <!-- Hero -->
    <div class="hero shadow-sm mb-4">
      <div class="hero-bg"></div>
      <div class="hero-body d-flex align-items-center justify-content-between flex-wrap">
        <div class="d-flex align-items-center">
          <div class="hero-icon mr-3"><i class="i-Cloud-Upload"></i></div>
          <div>
            <h3 class="mb-1">Import Products</h3>
            <div class="text-muted small">Bulk add items from an Excel file.</div>
          </div>
        </div>
        <router-link :to="{ name: 'index_products' }" class="btn btn-outline-secondary btn-sm mt-3 mt-sm-0">
          <i class="i-Left"></i> Back to list
        </router-link>
      </div>
    </div>

    <b-card class="shadow-sm">
      <!-- Type selector (single stays outline, variant becomes solid when active) -->
      <div class="d-flex justify-content-center mb-3">
        <b-button-group size="sm" class="seg">
          <!-- Always outline-primary for Single (as requested) -->
          <b-button :variant="importType==='single' ? 'primary' : 'outline-primary'" 
            @click="switchType('single')">
            <i class="i-Shop mr-1"></i>Single Products
          </b-button>
          <!-- Variant toggles to solid primary when active -->
          <b-button :variant="importType==='variant' ? 'primary' : 'outline-primary'"
                    @click="switchType('variant')">
            <i class="i-Library mr-1"></i>Variant Products
          </b-button>
        </b-button-group>
      </div>

      <b-row>
        <!-- Upload column -->
        <b-col md="7" class="mb-4">
          <div
            class="dropzone"
            :class="{ 'is-dragover': isDragOver, 'has-file': file }"
            @dragover.prevent="onDragOver"
            @dragleave.prevent="onDragLeave"
            @drop.prevent="onDrop"
            @click="browse"
          >
            <input ref="file" type="file" class="d-none" @change="onFileSelected" :accept="accept" />
            <div class="dz-inner text-center">
              <div class="dz-icon mb-2"><i class="i-Download-Window"></i></div>
              <h5 class="mb-2">Click or drop your Excel file here</h5>
              <div class="text-muted small">
                Allowed formats: XLSX, XLS · Max size: 20MB
              </div>

              <!-- Selected file pill -->
              <div v-if="file" class="file-pill mt-3 d-inline-flex align-items-center">
                <div class="file-dot mr-2"></div>
                <div class="file-meta mr-3">
                  <div class="file-name">{{ fileName }}</div>
                  <div class="file-size text-muted small">{{ prettySize }}</div>
                </div>
                <b-button size="sm" variant="outline-danger" @click.stop="clearFile">
                  Remove
                </b-button>
              </div>
            </div>
          </div>

          <!-- Example format (non-technical) -->
          <b-card class="mt-3">
            <div class="d-flex align-items-center mb-2">
              <i class="i-Information mr-2 text-primary"></i>
              <h6 class="mb-0">Example format</h6>
            </div>

            <!-- Single example -->
            <div v-if="importType==='single'">
              <p class="small text-muted mb-2">
                Create one row per product. Columns in <span class="badge badge-success-soft">green</span> are required.
              </p>
              <div class="table-responsive">
                <table class="table table-sm table-bordered example-table">
                  <thead class="thead-light">
                    <tr>
                      <th class="req">name</th>
                      <th class="req">code</th>
                      <th class="req">price</th>
                      <th class="req">cost</th>
                      <th class="req">category</th>
                      <th class="req">unit</th>
                      <th>brand</th>
                      <th>stock_alert</th>
                      <th>note</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Blue T-Shirt</td>
                      <td>TSHIRT-BLUE</td>
                      <td>19.90</td>
                      <td>8.00</td>
                      <td>Apparel</td>
                      <td>pc</td>
                      <td>Acme</td>
                      <td>5</td>
                      <td>Summer collection</td>
                    </tr>
                    <tr>
                      <td>Coffee Mug</td>
                      <td>MUG-COF-01</td>
                      <td>6.50</td>
                      <td>2.20</td>
                      <td>Home</td>
                      <td>pc</td>
                      <td></td>
                      <td>0</td>
                      <td></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <ul class="mini-notes mt-2">
                <li><strong>code</strong> must be unique across products and variants.</li>
                <li><strong>unit</strong> must already exist (use its short name if available).</li>
                <li><strong>category</strong> will be created automatically if missing.</li>
              </ul>
            </div>

            <!-- Variant example -->
            <div v-else>
              <p class="small text-muted mb-2">
                Create one row per variant. Repeat the product columns for each variant of the same product_code.
                Columns in <span class="badge badge-success-soft">green</span> are required.
              </p>
              <div class="table-responsive">
                <table class="table table-sm table-bordered example-table">
                  <thead class="thead-light">
                    <tr>
                      <th class="req">product_name</th>
                      <th class="req">product_code</th>
                      <th class="req">category</th>
                      <th class="req">unit</th>
                      <th>brand</th>
                      <th class="req">variant_name</th>
                      <th class="req">variant_code</th>
                      <th class="req">variant_cost</th>
                      <th class="req">variant_price</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>T-Shirt</td>
                      <td>TSHIRT-100</td>
                      <td>Apparel</td>
                      <td>pc</td>
                      <td>Acme</td>
                      <td>Small</td>
                      <td>TSHIRT-100-S</td>
                      <td>7.50</td>
                      <td>14.90</td>
                    </tr>
                    <tr>
                      <td>T-Shirt</td>
                      <td>TSHIRT-100</td>
                      <td>Apparel</td>
                      <td>pc</td>
                      <td>Acme</td>
                      <td>Medium</td>
                      <td>TSHIRT-100-M</td>
                      <td>7.50</td>
                      <td>14.90</td>
                    </tr>
                    <tr>
                      <td>T-Shirt</td>
                      <td>TSHIRT-100</td>
                      <td>Apparel</td>
                      <td>pc</td>
                      <td>Acme</td>
                      <td>Large</td>
                      <td>TSHIRT-100-L</td>
                      <td>7.50</td>
                      <td>14.90</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <ul class="mini-notes mt-2">
                <li><strong>product_code</strong>  Parent product code used to group variants.</li>
                <li><strong>variant_code</strong> must be unique globally (cannot match any product or variant code).</li>
                <li><strong>unit</strong> must already exist (use its short name if available).</li>
                <li><strong>category</strong> will be created automatically if missing.</li>
              </ul>
            </div>
          </b-card>

          <!-- MULTI-ERROR PANEL -->
          <b-alert v-if="errorMessages.length" show variant="danger" class="mt-3">
            <div class="d-flex align-items-start">
              <i class="i-Close-Window mr-2 mt-1"></i>
              <div>
                <div class="font-weight-bold mb-1">Import failed. Fix the issues below:</div>
                <ul class="mb-0 pl-3">
                  <li v-for="(err, idx) in errorMessages" :key="'err-'+idx">{{ err }}</li>
                </ul>
              </div>
            </div>
          </b-alert>

          <!-- Optional warnings list -->
          <b-alert v-if="warningMessages.length" show variant="warning" class="mt-3">
            <div class="d-flex align-items-start">
              <i class="i-Information mr-2 mt-1"></i>
              <div>
                <div class="font-weight-bold mb-1">Warnings</div>
                <ul class="mb-0 pl-3">
                  <li v-for="(w, idx) in warningMessages" :key="'warn-'+idx">{{ w }}</li>
                </ul>
              </div>
            </div>
          </b-alert>

          <!-- Progress -->
          <div v-if="uploading" class="mt-3">
            <div class="d-flex justify-content-between mb-1">
              <small class="text-muted">Uploading</small>
              <small>{{ progress }}%</small>
            </div>
            <b-progress :value="progress" height="8px"></b-progress>
          </div>

          <!-- Actions -->
          <div class="d-flex flex-wrap align-items-center mt-3">
            <b-button
              variant="primary"
              size="sm"
              class="mr-2 mb-2"
              :disabled="!canSubmit || uploading"
              @click="submit"
            >
              <span v-if="!uploading"><i class="i-Upload mr-1"></i>Import now</span>
              <span v-else class="d-inline-flex align-items-center">
                <span class="spinner sm spinner-white mr-2"></span>Processing…
              </span>
            </b-button>

            <a :href="exampleHref" class="btn btn-outline-info btn-sm mr-2 mb-2" target="_blank" rel="noopener">
              <i class="i-File-Excel mr-1"></i>Download example
            </a>

            <b-button
              variant="outline-secondary"
              size="sm"
              class="mb-2"
              :disabled="!file || uploading"
              @click="clearFile"
            >
              <i class="i-Power-2 mr-1"></i>Reset
            </b-button>
          </div>
        </b-col>

        <!-- Guide column -->
        <b-col md="5" class="mb-4">
          <b-card class="mb-3">
            <h6 class="mb-2">Required & optional columns</h6>

            <!-- Singles -->
            <div v-if="importType==='single'">
              <div class="chip-grid">
                <span v-for="c in singlesGuide" :key="c.key"
                      class="chip" :class="c.required ? 'chip-req' : 'chip-opt'">
                  {{ c.label }}
                </span>
              </div>
              <ul class="mini-notes mt-3">
                <li><strong>code</strong> — Must be unique across products and variants.</li>
                <li><strong>unit</strong> — Unit must already exist · Use the unit short name when possible.</li>
                <li><strong>category</strong> — Will be created if missing.</li>
              </ul>
            </div>

            <!-- Variants -->
            <div v-else>
              <div class="chip-grid">
                <span v-for="c in variantsGuide" :key="c.key"
                      class="chip" :class="c.required ? 'chip-req' : 'chip-opt'">
                  {{ c.label }}
                </span>
              </div>
              <ul class="mini-notes mt-3">
                <li><strong>product_code</strong> — Parent product code used to group variants.</li>
                <li><strong>variant_code</strong> — Variant code must be globally unique.</li>
                <li><strong>unit</strong> — Unit must already exist · Use the unit short name when possible.</li>
                <li><strong>category</strong> — Will be created if missing.</li>
              </ul>
            </div>
          </b-card>

          <b-alert show variant="light" class="border">
            <div class="d-flex">
              <div class="tip-badge mr-2"><i class="i-Information"></i></div>
              <div>
                <strong>Heads up</strong>
                <div class="small text-muted">Large files may take longer to process.</div>
              </div>
            </div>
          </b-alert>
        </b-col>
      </b-row>
    </b-card>
  </div>
</template>

<script>
import NProgress from 'nprogress';
// axios assumed globally available

export default {
  name: 'ImportProductsPage',
  data() {
    return {
      // endpoints
      singleEndpoint: 'products/import/single',
      variantEndpoint: 'products/import/variants',

      // default selection: keep variant active (solid) and single always outline
      importType: 'single',

      // file state
      file: null,
      fileName: '',
      fileSize: 0,

      // ui state
      uploading: false,
      progress: 0,

      // multi-error support
      errorMessages: [],
      warningMessages: [],

      // dnd
      isDragOver: false,

      // limits
      maxSize: 20 * 1024 * 1024, // 20MB
      accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,.xlsx,.xls',

      // guides (UI-only help)
      singlesGuide: [
        { key: 'name',        label: 'name',        required: true },
        { key: 'code',        label: 'code',        required: true },
        { key: 'price',       label: 'price',       required: true },
        { key: 'cost',        label: 'cost',        required: true },
        { key: 'category',    label: 'category',    required: true },
        { key: 'unit',        label: 'unit',        required: true },
        { key: 'brand',       label: 'brand',       required: false },
        { key: 'stock_alert', label: 'stock_alert', required: false },
        { key: 'note',        label: 'note',        required: false }
      ],
      variantsGuide: [
        { key: 'product_name',  label: 'product_name',                 required: true },
        { key: 'product_code',  label: 'product_code',                 required: true },
        { key: 'category',      label: 'category',                     required: true },
        { key: 'unit',          label: 'unit',                         required: true },
        { key: 'brand',         label: 'brand',                        required: false },
        { key: 'variant_name',  label: 'variant_name',                 required: true },
        { key: 'variant_code',  label: 'variant_code',                 required: true },
        { key: 'variant_cost',  label: 'variant_cost',                 required: true },
        { key: 'variant_price', label: 'variant_price',                required: true }
      ]
    };
  },
  computed: {
    canSubmit() {
      return !!this.file && this.errorMessages.length === 0;
    },
    prettySize() {
      return this.formatBytes(this.fileSize);
    },
    exampleHref() {
      return this.importType === 'single'
        ? '/import/exemples/single_products.xlsx'
        : '/import/exemples/variant_products.xlsx';
    }
  },
  methods: {
    // ---------- UI helpers ----------
    toast(msg, title, variant) {
      if (this.$root && this.$root.$bvToast) {
        this.$root.$bvToast.toast(msg, { title: title, variant: variant, solid: true });
      }
    },
    switchType(type) {
      this.importType = type;
      // keep file, but clear previous error panel to avoid confusion
      this.clearErrors();
    },

    // ---------- DnD + browse ----------
    onDragOver() { this.isDragOver = true; },
    onDragLeave() { this.isDragOver = false; },
    onDrop(e) {
      this.isDragOver = false;
      var f = e && e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0] ? e.dataTransfer.files[0] : null;
      if (f) this.loadFile(f);
    },
    browse() {
      if (this.uploading) return;
      if (this.$refs && this.$refs.file) this.$refs.file.click();
    },
    onFileSelected(e) {
      const f = e && e.target && e.target.files && e.target.files[0] ? e.target.files[0] : null;
      if (!f) return;
      this.loadFile(f);
    },

    // ---------- File load + checks ----------
    loadFile(f) {
      this.clearErrors();
      const msgs = [];

      if (f.size > this.maxSize) {
        msgs.push('File is too large. Please upload a file under the 20MB limit.');
      }
      const name = f.name || '';
      const ext = name.split('.').pop().toLowerCase();
      if (['xlsx','xls'].indexOf(ext) === -1) {
        msgs.push('Unsupported file type. Please upload an .xlsx or .xls file.');
      }

      if (msgs.length) {
        this.errorMessages = msgs;
        // do not keep invalid file
        this.clearFile(false);
        return;
      }

      this.file = f;
      this.fileName = f.name;
      this.fileSize = f.size;
    },
    clearFile(resetInput) {
      if (typeof resetInput === 'undefined') resetInput = true;
      this.file = null; this.fileName = ''; this.fileSize = 0;
      if (resetInput && this.$refs && this.$refs.file) this.$refs.file.value = '';
    },
    clearErrors() {
      this.errorMessages = [];
      this.warningMessages = [];
    },
    formatBytes(bytes) {
      if (!bytes || bytes <= 0) return '0 B';
      var k = 1024; var sizes = ['B','KB','MB','GB','TB'];
      var i = Math.floor(Math.log(bytes) / Math.log(k));
      var v = (bytes / Math.pow(k, i)).toFixed(2);
      return v + ' ' + sizes[i];
    },

    // ---------- Error collectors ----------
    flattenLaravelErrors(errorsObj) {
      const out = [];
      if (!errorsObj || typeof errorsObj !== 'object') return out;
      Object.keys(errorsObj).forEach(k => {
        const v = errorsObj[k];
        if (Array.isArray(v)) {
          v.forEach(m => { if (m) out.push(String(m)); });
        } else if (v) {
          out.push(String(v));
        }
      });
      return out;
    },
    collectErrorsFromResponse(data) {
      const out = [];
      if (!data || typeof data !== 'object') return out;

      if (Array.isArray(data.messages)) {
        data.messages.forEach(m => { if (m) out.push(String(m)); });
      }
      if (data.message) {
        out.push(String(data.message));
      }
      if (data.errors) {
        out.push(...this.flattenLaravelErrors(data.errors));
      }
      if (data.details) {
        if (Array.isArray(data.details)) {
          data.details.forEach(m => { if (m) out.push(String(m)); });
        } else if (typeof data.details === 'string') {
          out.push(data.details);
        }
      }
      if (data.error && typeof data.error === 'string') {
        out.push(data.error);
      }

      // unique list
      const seen = {};
      return out.filter(m => (seen[m] ? false : (seen[m] = true)));
    },
    collectErrorsFromAxios(err) {
      if (err && err.response && err.response.status === 422) {
        const payload = err.response.data || {};
        const list = []
          .concat(this.flattenLaravelErrors(payload.errors))
          .concat(payload.message ? [String(payload.message)] : []);
        return list.length ? list : ['Validation failed. Please check your file and try again.'];
      }

      const payload = err && err.response ? err.response.data : null;
      const list = this.collectErrorsFromResponse(payload);
      if (list.length) return list;

      if (err && err.message) return [String(err.message)];
      return ['Something went wrong while uploading. Please try again.'];
    },

    // ---------- Submit ----------
    async submit() {
      if (!this.file) {
        this.errorMessages = ['Please choose a file to import.'];
        return;
      }

      this.clearErrors();
      this.uploading = true;
      this.progress = 0;
      NProgress.start(); NProgress.set(0.2);

      try {
        var fd = new FormData();
        fd.append('products', this.file);
        fd.append('type', this.importType === 'single' ? 'is_single' : 'is_variant');

        var endpoint = this.importType === 'single' ? this.singleEndpoint : this.variantEndpoint;

        const self = this;
        const response = await axios.post(endpoint, fd, {
          headers: { 'Content-Type': 'multipart/form-data' },
          onUploadProgress: function (pe) {
            if (pe && pe.total) {
              self.progress = Math.round((pe.loaded * 100) / pe.total);
            }
          }
        });

        const data = response && response.data ? response.data : null;
        const ok = data && (data.status === true || data.success === true);

        if (!ok) {
          const msgs = this.collectErrorsFromResponse(data);
          this.errorMessages = msgs.length ? msgs : ['Import failed. Please review your file and try again.'];
          this.toast('Check the error list and fix your file.', 'Import failed', 'danger');
          return;
        }

        this.toast('Imported successfully.', 'Success', 'success');
        this.$router.push({ name: 'index_products' });

      } catch (err) {
        this.errorMessages = this.collectErrorsFromAxios(err);
        this.toast('Check the error list and fix your file.', 'Import failed', 'danger');
      } 
      finally {
        NProgress.done();
        this.uploading = false;
        this.progress = 0;
      }
    }
  }
};
</script>

<style scoped>
/* Hero */
.hero{position:relative;border-radius:12px;overflow:hidden}
.hero-bg{position:absolute;inset:0;background:linear-gradient(135deg,#e6f0ff 0%,#f7fbff 60%,#ffffff 100%);opacity:.9}
.hero-body{position:relative;padding:1.1rem 1.1rem}
.hero-icon{width:44px;height:44px;border-radius:12px;background:#2667ff10;color:#2667ff;display:inline-grid;place-items:center;font-size:20px}

/* Segmented control */
.seg .btn{min-width:160px}

/* Dropzone */
.dropzone{border:2px dashed #cfd8e3;border-radius:14px;padding:28px 18px;cursor:pointer;transition:all .15s ease;background:#fbfdff}
.dropzone:hover{border-color:#9cb4ff;background:#f7fbff;box-shadow:0 1px 6px rgba(38,103,255,.08)}
.dropzone.is-dragover{border-color:#2667ff;background:#f1f6ff}
.dropzone.has-file{border-color:#cfd8e3}
.dz-icon{font-size:28px;color:#2667ff}

/* File pill */
.file-pill{border:1px solid #e6ebf2;border-radius:999px;padding:8px 12px;background:#fff}
.file-dot{width:10px;height:10px;background:#2667ff;border-radius:999px}
.file-name{font-weight:600}

/* Example badges */
.badge-success-soft{background:#eaf7ef;color:#0a7a2d;border:1px solid #cdebd7;font-weight:600}

/* Example table */
.example-table th.req{background:#eaf7ef;border-color:#cdebd7}
.example-table thead th{font-weight:600}

/* Chips grid */
.chip-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));grid-gap:8px}
@media (min-width:992px){.chip-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.chip{display:inline-block;padding:6px 10px;border-radius:999px;font-size:.85rem;font-weight:600;border:1px solid transparent;white-space:nowrap;text-overflow:ellipsis;overflow:hidden}
.chip-req{color:#0a7a2d;background:#eaf7ef;border-color:#cdebd7}
.chip-opt{color:#475569;background:#f5f7fb;border-color:#e6e9f2}

/* Notes */
.mini-notes{padding-left:18px;margin:0}
.mini-notes li{margin-bottom:6px}

/* Tip badge */
.tip-badge{width:28px;height:28px;border-radius:8px;background:#f1f5ff;color:#2667ff;display:inline-grid;place-items:center;font-size:14px}
</style>
