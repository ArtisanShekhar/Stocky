<template>
  <div class="main-content">
    <breadcumb :page="$t('Task_List')" :folder="$t('Tasks')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    
    <div v-else>

      <b-row>
        <!-- ICON BG -->

        <b-col lg="3" md="6" sm="12">
            <b-card class="card-icon-bg card-icon-bg-primary o-hidden mb-30 text-center">
              <i class="i-Yes"></i>
              <div class="content">
                <p class="text-muted mt-2 mb-0">{{$t('complete')}}</p>
                <p
                  class="text-primary text-24 line-height-1 mb-2"
                >{{count_completed}}</p>
              </div>
            </b-card>
        </b-col>

        <b-col lg="3" md="6" sm="12">
            <b-card class="card-icon-bg card-icon-bg-primary o-hidden mb-30 text-center">
              <i class="i-Pause"></i>
              <div class="content">
                <p class="text-muted mt-2 mb-0">{{$t('Not_Started')}}</p>
                <p
                  class="text-primary text-24 line-height-1 mb-2"
                >{{count_not_started}}</p>
              </div>
            </b-card>
        </b-col>

        <b-col lg="3" md="6" sm="12">
            <b-card class="card-icon-bg card-icon-bg-primary o-hidden mb-30 text-center">
              <i class="i-Loading-3"></i>
              <div class="content">
                <p class="text-muted mt-2 mb-0">{{$t('In_Progress')}}</p>
                <p
                  class="text-primary text-24 line-height-1 mb-2"
                >{{count_in_progress}}</p>
              </div>
            </b-card>
        </b-col>

        <b-col lg="3" md="6" sm="12">
            <b-card class="card-icon-bg card-icon-bg-primary o-hidden mb-30 text-center">
              <i class="i-Close"></i>
              <div class="content">
                <p class="text-muted mt-2 mb-0">{{$t('Cancelled')}}</p>
                <p
                  class="text-primary text-24 line-height-1 mb-2"
                >{{count_cancelled}}</p>
              </div>
            </b-card>
        </b-col>

      </b-row>

      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="tasks"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
          enabled: true,
          placeholder: $t('Search_this_table'),  
        }"
        :pagination-options="{
        enabled: true,
        mode: 'records',
        nextLabel: 'next',
        prevLabel: 'prev',
      }"
        styleClass="tableOne table-hover vgt-table"
      >
        
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button variant="outline-info ripple m-1" size="sm" v-b-toggle.sidebar-right>
            <i class="i-Filter-2"></i>
            {{ $t("Filter") }}
          </b-button>
          <b-button @click="Task_PDF()" size="sm" variant="outline-success ripple m-1">
            <i class="i-File-Copy"></i> PDF
          </b-button>
           <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger ripple m-1"
              :data="tasks"
              :columns="columns"
              :file-name="'Tasks'"
              :file-type="'xlsx'"
              :sheet-name="'Tasks'"
              >
              <i class="i-File-Excel"></i> EXCEL
          </vue-excel-xlsx>
          <router-link
            class="btn-sm btn btn-primary ripple btn-icon m-1"
            to="/app/tasks/store"
          >
            <span class="ul-btn__icon">
              <i class="i-Add"></i>
            </span>
            <span class="ul-btn__text ml-1">{{$t('Add')}}</span>
          </router-link>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'actions'">

            <router-link
              title="Edit"
              v-b-tooltip.hover
              :to="'/app/tasks/edit/'+props.row.id"
            >
              <i class="i-Edit text-25 text-success"></i>
            </router-link>

            <a
              title="Delete"
              class="cursor-pointer"
              v-b-tooltip.hover
              @click="Remove_Task(props.row.id)"
            >
              <i class="i-Close-Window text-25 text-danger"></i>
            </a>
          </span>

          <div v-else-if="props.column.field == 'status'">
            <span
              v-if="props.row.status == 'completed'"
              class="badge badge-outline-success"
            >{{$t('complete')}}</span>
            <span
              v-else-if="props.row.status == 'not_started'"
              class="badge badge-outline-warning"
            >{{$t('Not_Started')}}</span>
            <span
              v-else-if="props.row.status == 'progress'"
              class="badge badge-outline-primary"
            >{{$t('In_Progress')}}</span>

            <span
              v-else-if="props.row.status == 'cancelled'"
              class="badge badge-outline-danger"
            >{{$t('Cancelled')}}</span>

            <span
              v-else-if="props.row.status == 'hold'"
              class="badge badge-outline-secondary"
            >{{$t('On_Hold')}}</span>
          </div>
        </template>
      </vue-good-table>
    </div>

    <!-- Multiple Filters -->
    <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
      <div class="px-3 py-2">
        <b-row>
          <!-- Start Date  -->
          <b-col md="12">
            <b-form-group :label="$t('start_date')">
              <b-form-input type="date" v-model="Filter_start_date"></b-form-input>
            </b-form-group>
          </b-col>

           <!-- End Date  -->
           <b-col md="12">
            <b-form-group :label="$t('Finish_Date')">
              <b-form-input type="date" v-model="Filter_end_date"></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Project  -->
          <b-col md="12">
            <b-form-group :label="$t('Project')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Project')"
                v-model="Filter_Project"
                :options="projects.map(projects => ({label: projects.title, value: projects.id}))"
              />
            </b-form-group>
          </b-col>

          <!-- company  -->
          <b-col md="12">
            <b-form-group :label="$t('Company')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Company')"
                v-model="Filter_Company"
                :options="companies.map(companies => ({label: companies.name, value: companies.id}))"
              />
            </b-form-group>
          </b-col>

           <!-- Status  -->
           <b-col md="12">
            <b-form-group :label="$t('Status')">
              <v-select
                v-model="Filter_status"
                :reduce="label => label.value"
                :placeholder="$t('Choose_Status')"
                :options="
                      [
                        {label: 'Completed', value: 'completed'},
                        {label: 'Not Started', value: 'not_started'},
                        {label: 'In Progress', value: 'progress'},
                        {label: 'Cancelled', value: 'cancelled'},
                        {label: 'On Hold', value: 'hold'},
                      ]"
              ></v-select>
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12">
            <b-button
              @click="Get_Tasks(serverParams.page)"
              variant="primary m-1"
              size="sm"
              block
            >
              <i class="i-Filter-2"></i>
              {{ $t("Filter") }}
            </b-button>
          </b-col>
          <b-col md="6" sm="12">
            <b-button @click="Reset_Filter()" variant="danger m-1" size="sm" block>
              <i class="i-Power-2"></i>
              {{ $t("Reset") }}
            </b-button>
          </b-col>
        </b-row>
      </div>
    </b-sidebar>
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";
import jsPDF from "jspdf";
import "jspdf-autotable";

export default {
  metaInfo: {
    title: "Tasks"
  },
  data() {
    return {
      isLoading: true,
      serverParams: {
        columnFilters: {},
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      selectedIds: [],
      totalRows: "",
      search: "",
      limit: "10",
      Filter_start_date: "",
      Filter_end_date: "",
      Filter_status: "",
      Filter_Project: "",
      Filter_Company: "",
      projects: [],
      tasks: [],
      companies: [],
      count_not_started: "",
      count_in_progress: "",
      count_cancelled: "",
      count_completed: "",
    };
  },

  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"]),
    columns() {
      return [
        {
          label: this.$t("title"),
          field: "title",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Project"),
          field: "project_title",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Company"),
          field: "company_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("start_date"),
          field: "start_date",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Finish_Date"),
          field: "end_date",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Status"),
          field: "status",
          tdClass: "text-left",
          thClass: "text-left"
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
    //---------------------- Tasks PDF -------------------------------\\
    Task_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      pdf.addFont(fontPath, "VazirmatnBold", "bold"); 
      pdf.setFont("VazirmatnBold"); 

      let columns = [
        { title: self.$t("title"), dataKey: "title" },
        { title: self.$t("Project"), dataKey: "project_title" },
        { title: self.$t("Company"), dataKey: "company_name" },
        { title: self.$t("start_date"), dataKey: "start_date" },
        { title: self.$t("Finish_Date"), dataKey: "end_date" }
      ];

      pdf.autoTable({
        columns: columns,
        body: self.deposits,
        foot: footer,
        startY: 70,
        theme: "grid", 
        didDrawPage: (data) => {
          pdf.setFont("VazirmatnBold");
          pdf.setFontSize(18);
          pdf.text("Task List", 40, 25);   
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

      pdf.save("Task_List.pdf");
    },

    //------ update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Tasks(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Tasks(1);
      }
    },


    //------ Event Sort Change
    onSortChange(params) {
      let field = "";
      if (params[0].field == "project_title") {
        field = "project_id";
      } else if (params[0].field == "company_name") {
        field = "company_id";
      } else {
        field = params[0].field;
      }
      this.updateParams({
        sort: {
          type: params[0].type,
          field: field
        }
      });
      this.Get_Tasks(this.serverParams.page);
    },

    //------ Event Search
    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Tasks(this.serverParams.page);
    },

    //------ Reset Filter
    Reset_Filter() {
      this.search = "";
      this.Filter_start_date = "";
      this.Filter_end_date = "";
      this.Filter_status = "";
      this.Filter_Project = "";
      this.Filter_Company = "";
      this.Get_Tasks(this.serverParams.page);
    },

    // Simply replaces null values with strings=''
    setToStrings() {
      if (this.Filter_Project === null) {
        this.Filter_Project = "";
      } else if (this.Filter_Company === null) {
        this.Filter_Company = "";
      }
    },

    //------------------------------------------------ Get All tasks -------------------------------\\
    Get_Tasks(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.setToStrings();
      axios
        .get(
          "tasks?page=" +
            page +
            "&status=" +
            this.Filter_status +
            "&project_id=" +
            this.Filter_Project +
            "&start_date=" +
            this.Filter_start_date +
            "&end_date=" +
            this.Filter_end_date +
            "&company_id=" +
            this.Filter_Company +
            "&SortField=" +
            this.serverParams.sort.field +
            "&SortType=" +
            this.serverParams.sort.type +
            "&search=" +
            this.search +
            "&limit=" +
            this.limit
        )
        .then(response => {
          this.projects = response.data.projects;
          this.companies = response.data.companies;
          this.tasks = response.data.tasks;
          this.totalRows = response.data.totalRows;
          this.count_not_started = response.data.count_not_started;
          this.count_in_progress = response.data.count_in_progress;
          this.count_cancelled = response.data.count_cancelled;
          this.count_completed = response.data.count_completed;

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
    },

    //------------------------------- Remove Task -------------------------\\

    Remove_Task(id) {
      this.$swal({
        title: this.$t("Delete_Title"),
        text: this.$t("Delete_Text"),
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Delete_cancelButtonText"),
        confirmButtonText: this.$t("Delete_confirmButtonText")
      }).then(result => {
        if (result.value) {
          // Start the progress bar.
          NProgress.start();
          NProgress.set(0.1);
          axios
            .delete("tasks/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              Fire.$emit("event_delete_tasks");
            })
            .catch(() => {
              // Complete the animation of theprogress bar.
              setTimeout(() => NProgress.done(), 500);
              this.$swal(
                this.$t("Delete_Failed"),
                this.$t("Delete_Therewassomethingwronge"),
                "warning"
              );
            });
        }
      });
    },

  },

  //----------------------------- Created function-------------------
  created: function() {
    this.Get_Tasks(1);

    Fire.$on("event_delete_tasks", () => {
      setTimeout(() => {
        // Complete the animation of theprogress bar.
        NProgress.done();
        this.Get_Tasks(this.serverParams.page);
      }, 500);
    });
  }
};
</script>