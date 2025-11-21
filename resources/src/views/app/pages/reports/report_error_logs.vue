<template>
  <div class="main-content">
    <breadcumb :page="$t('Error_Logs')" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <vue-good-table
      v-if="!isLoading"
      mode="remote"
      :columns="columns"
      :totalRows="totalRows"
      :rows="logs"
      :pagination-options="{
        enabled: true,
        mode: 'records',
        perPage: perPage,
        nextLabel: 'Next',
        prevLabel: 'Prev',
      }"
      @on-page-change="onPageChange"
      @on-per-page-change="onPerPageChange"
      styleClass="table-hover tableOne vgt-table"
    >
      <div slot="table-actions" class="mt-2 mb-3">
       
      </div>

      <template slot="table-row" slot-scope="props">
        <div v-if="props.column.field === 'details'">
          <pre style="max-height: 100px; overflow-y: auto; white-space: pre-wrap;">{{ props.row.details }}</pre>
        </div>
      </template>
    </vue-good-table>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      logs: [],
      totalRows: 0,
      isLoading: true,
      perPage: 10,
      currentPage: 1,
      columns: [
        // { label: "ID", field: "id", sortable: true },
        { label: this.$t("Context"), field: "context", sortable: true },
        { label: this.$t("Message"), field: "message", sortable: false },
        { label: this.$t("Details"), field: "details", sortable: false },
        { label: this.$t("Occurred_At"), field: "occurred_at", sortable: true },
      ]
    };
  },
  mounted() {
    this.fetchLogs();
  },
  methods: {
    fetchLogs(page = 1) {
      this.isLoading = true;
      axios
        .get("/error-logs", {
          params: {
            page: page,
            per_page: this.perPage,
          }
        })
        .then(response => {
          this.logs = response.data.logs;
          this.totalRows = response.data.total;
        })
        .catch(error => {
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
    onPageChange(params) {
      this.currentPage = params.currentPage;
      this.fetchLogs(this.currentPage);
    },
    onPerPageChange(params) {
      this.perPage = params.currentPerPage;
      this.fetchLogs(this.currentPage);
    },

  }
};
</script>
