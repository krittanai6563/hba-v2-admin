<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import * as XLSX from 'xlsx';

// Reactive Variables
const selectedYear = ref((new Date().getFullYear() + 543).toString());
const selectedQuarter = ref('');
const selectedMonth = ref('');

// Fetching error and region data
const fetchError = ref('');
const regionData = ref<Record<string, { unit: number; total_value: number; usable_area: number }>>({});

// User role and ID (from localStorage)
const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user';

const yearOptions = computed(() => {
    const current = new Date().getFullYear() + 543;
    return Array.from({ length: 6 }, (_, i) => (current - i).toString());
});


const quarterOptions = ['ทั้งหมด', 'ไตรมาสที่ 1', 'ไตรมาสที่ 2', 'ไตรมาสที่ 3', 'ไตรมาสที่ 4'];

// Month Mapping
const monthMap: { [key: string]: number } = {
    'มกราคม': 1,
    'กุมภาพันธ์': 2,
    'มีนาคม': 3,
    'เมษายน': 4,
    'พฤษภาคม': 5,
    'มิถุนายน': 6,
    'กรกฎาคม': 7,
    'สิงหาคม': 8,
    'กันยายน': 9,
    'ตุลาคม': 10,
    'พฤศจิกายน': 11,
    'ธันวาคม': 12
};
const monthOptions = Object.keys(monthMap);

// Regions and Data fetching
const regions = [
    'กรุงเทพปริมณฑล', 'ภาคเหนือ', 'ภาคตะวันออกเฉียงเหนือ',
    'ภาคกลาง', 'ภาคตะวันออก', 'ภาคใต้', 'ภาคตะวันตก'
];

// Fetch data from server
const fetchRegionSummary = async () => {
    if (!userId || !selectedYear.value) return;
    fetchError.value = '';
    try {
        const res = await fetch('https://6e9fdf451a56.ngrok-free.app/package/backend/get_region_summary.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: userId,
                buddhist_year: selectedYear.value,
                quarter: selectedQuarter.value !== 'ทั้งหมด' ? selectedQuarter.value : undefined,
                month: selectedMonth.value ? monthMap[selectedMonth.value] : undefined, // Send numeric month
                role: userRole // Send role as well
            })
        });

        const data = await res.json();
        if (data.error) {
            fetchError.value = `เกิดข้อผิดพลาด: ${data.error}`;
            regionData.value = {};
        } else {
            regionData.value = data;
        }
    } catch (err: any) {
        console.error('Error fetching region data:', err);
        fetchError.value = 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้';
    }
};

// Watches
watch(selectedYear, fetchRegionSummary, { immediate: true });
watch(selectedQuarter, fetchRegionSummary);
watch(selectedMonth, fetchRegionSummary); // Watch the month selection for changes

// Function to get region data fields
type RegionField = 'unit' | 'total_value' | 'usable_area' | 'price_per_sqm';
const getRegionField = (region: string, field: RegionField): number => {
    const entry = regionData.value[region];
    if (!entry) return 0;

    return field === 'price_per_sqm'
        ? entry.usable_area > 0
            ? entry.total_value / entry.usable_area
            : 0
        : entry[field];
};

// Chart Data
const chartLabels = computed(() => regions);

const chartSeries = computed(() => {
    return [
        {
            name: 'จำนวนหลัง',
            type: 'column',  // Set type to column (bar graph)
            data: regions.map(region => getRegionField(region, 'unit')),
            color: '#f9ce1d',  // Set color for this column
        },
        {
            name: 'พื้นที่ใช้สอย',
            type: 'column', // Set type to column (bar graph)
            data: regions.map(region => getRegionField(region, 'usable_area')),
            color: '#4caf50',  // Set color for this column
        },
        {
            name: 'มูลค่ารวม',
            type: 'line', // Set type to line (line graph)
            data: regions.map(region => getRegionField(region, 'total_value')),
            color: '#3f51b5',  // Set color for this line
        },
    ];
});


// Chart options setup
const chartOptions = ref({
    chart: {
        height: 350,
        type: 'line', // Set the default chart type to 'line'
        stacked: false,
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
        toolbar: {
            show: true,
            tools: {
                download: true, // Enable only the download tool
            },
        },
    },
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '70%',
            dataLabels: {
                position: 'top',
                offsetY: 0,
            },
        },
        line: {
            dataLabels: {
                position: 'top',
                offsetY: 0,
            },
            curve: 'smooth',
        },
    },

    dataLabels: {
        enabled: true,
        position: 'top',
        offsetY: -13,
        style: {
            fontSize: '10px',
        },
        formatter: (value: number) => {
            return value >= 1000 ? value.toLocaleString('th-TH') : value.toString();
        }
    },
    stroke: {
        width: [2, 2, 4],
        curve: 'smooth',
    },
    grid: {
        show: true,
        strokeDashArray: 4,
        borderColor: 'rgba(0, 0, 0, 0.1)',
    },
    xaxis: {
        categories: chartLabels.value,
    },
    yaxis: [
        {
            seriesName: 'จำนวนหลัง',
            axisTicks: { show: true },
            labels: {
                show: false,
                style: { colors: '#008FFB' },
            },
            tooltip: { enabled: true },
        },
        {
            seriesName: 'พื้นที่ใช้สอย',
            opposite: true,
            axisTicks: { show: true },
            labels: {
                show: false,
                style: { colors: '#00E396' },
            },
        },
        {
            seriesName: 'มูลค่ารวม',
            opposite: false,
            axisTicks: { show: true },
            labels: {
                show: false,
                style: { colors: '#FF4560' },
            },
        },
    ],
    tooltip: {
        fixed: {
            enabled: true,
            position: 'topLeft',
            offsetY: 0,
            offsetX: 0,
        },
    },
    legend: {
        horizontalAlign: 'center',
        offsetX: 0,
    },
});




const totalUnit = computed(() => {
    return regions.reduce((sum, region) => sum + getRegionField(region, 'unit'), 0);
});

const totalValue = computed(() => {
    return regions.reduce((sum, region) => sum + getRegionField(region, 'total_value'), 0);
});

const totalArea = computed(() => {
    return regions.reduce((sum, region) => sum + getRegionField(region, 'usable_area'), 0);
});

const averagePricePerSqm = computed(() => {
    const value = totalValue.value;
    const area = totalArea.value;
    return area > 0 ? +(value / area).toFixed(2) : 0;
});

const formatNumber = (value: number, isDecimal = false) => {
    return value.toLocaleString('th-TH', {
        minimumFractionDigits: isDecimal ? 2 : 0,
        maximumFractionDigits: isDecimal ? 2 : 0
    });
};


const exportToExcel = () => {
    // สร้างข้อมูลตารางที่ต้องการ export
    const tableData = regions.map(region => {
        return {
            Region: region,
            'จำนวนหลัง': getRegionField(region, 'unit'),
            'มูลค่ารวม': getRegionField(region, 'total_value'),
            'พื้นที่ใช้สอย': getRegionField(region, 'usable_area'),
            'ราคาเฉลี่ย/ตร.ม.': formatNumber(getRegionField(region, 'price_per_sqm'), true),
        };
    });

    const headerTitle = [
        [`ข้อมูลยอดเซ็นสัญญาแยกตามภูมิภาค ปี ${selectedYear.value} - 
        ${selectedQuarter && !selectedMonth ? selectedQuarter : selectedMonth ? 'เดือน ' + selectedMonth : 'ทั้งปี'}`]
    ];


    const ws = XLSX.utils.json_to_sheet(tableData);


    ws['!rows'] = [{ hpt: 30, hpx: 30 }];
    ws['A1'] = {
        v: `ข้อมูลยอดเซ็นสัญญาแยกตามภูมิภาค ปี ${selectedYear.value} - 
        ${selectedQuarter && !selectedMonth ? selectedQuarter : selectedMonth ? 'เดือน ' + selectedMonth : 'ทั้งปี'}`,
        t: 's',
        s: { font: { bold: true, sz: 14 }, alignment: { horizontal: 'center', vertical: 'center' }, border: { bottom: { style: 'thin', color: { rgb: '00A6D4' } } } }
    };


    const wb = XLSX.utils.book_new();


    XLSX.utils.book_append_sheet(wb, ws, 'Region Summary');


    const range = XLSX.utils.decode_range(ws['!ref']!);
    const colWidth = [20, 15, 15, 20, 20];


    for (let col = range.s.c; col <= range.e.c; col++) {
        ws[XLSX.utils.encode_col(col) + '1'].s = { font: { bold: true }, alignment: { horizontal: 'center' }, border: { bottom: { style: 'thin', color: { rgb: '00A6D4' } } } };
        ws['!cols'] = colWidth.map(width => ({ width }));
    }


    XLSX.writeFile(wb, `Region_Summary_${selectedYear.value}.xlsx`);
};

</script>


<template>
    <v-row>
        <v-col cols="12" sm="12" lg="12">

            <div class="mt-3 mb-6">
                <div class="d-flex justify-space-between">
                    <div class="d-flex py-0 align-center">
                        <div>
                            <h3 class="text-h5 card-title">รายงานยอดเซ็นสัญญาเปรียบเทียบตามพื้นที่</h3>
                            <ul
                                class="v-breadcrumbs v-breadcrumbs--density-default text-subtitle-1 textSecondary pa-0 ml-n1">
                                <!---->
                                <li class="v-breadcrumbs-item" text="Home"><a class="v-breadcrumbs-item--link" href="#">
                                        <p>หน้าแรก</p>
                                    </a></li>
                                <li class="v-breadcrumbs-divider"><i
                                        class="mdi-chevron-right mdi v-icon notranslate v-theme--BLUE_THEME"
                                        aria-hidden="true" style="font-size: 15px; height: 15px; width: 15px;"></i></li>
                                <li class="v-breadcrumbs-item" text="Dashboard"><a class="v-breadcrumbs-item--link"
                                        href="#">
                                        <p>รายงานยอดเซ็นสัญญาเปรียบเทียบตามพื้นที่</p>
                                    </a></li>
                            </ul>
                        </div>
                    </div>

                    <div>

                    </div>
                </div>
            </div>

        </v-col>




        <v-col cols="12" sm="12" lg="12">
            <v-card elevation="10">
                <v-card-text>
                    <v-row>
                        <v-col cols="12" sm="4" md="4">
                            <v-select v-model="selectedYear" :items="yearOptions" label="เลือกปี" clearable />
                        </v-col>
                        <v-col cols="12" sm="4" md="4" v-show="!selectedMonth">
                            <v-select v-model="selectedQuarter" :items="quarterOptions" label="เลือกไตรมาส" clearable />
                        </v-col>
                        <v-col cols="12" sm="4" md="4" v-show="!selectedQuarter">
                            <v-select v-model="selectedMonth" :items="monthOptions" label="เลือกเดือน" clearable />
                        </v-col>

                    </v-row>
                </v-card-text>
            </v-card>
        </v-col>




        <v-col cols="12" sm="12" lg="12">
            <VCard elevation="10" tyle="width: 100%; height: 100%;">
                <v-card-text>
                    <div class="d-sm-flex align-center">
                        <div>

                            <h3 class="card-title mb-1">สรุปยอดเซ็นสัญญาเปรียบเทียบตามพื้นที่ ประจำปี {{ selectedYear }}
                                -
                                <span v-if="selectedQuarter && !selectedMonth">{{ selectedQuarter }}</span>
                                <span v-if="selectedMonth && !selectedQuarter">เดือน {{ selectedMonth
                                }}</span>
                                <span v-if="!selectedQuarter && !selectedMonth">ทั้งปี</span>
                            </h3>
                            <h5 class="card-subtitle" style="text-align: left;">(หน่วย : ล้านบาท)</h5>

                        </div>
                        <div class="ms-auto mt-sm-0 mt-4">

                        </div>
                    </div>
                    <div class="mt-5">
                        <apexchart height="320" :options="chartOptions" :series="chartSeries"></apexchart>

                    </div>


                </v-card-text>
            </VCard>

        </v-col>



        <v-col cols="12">




            <v-alert v-if="fetchError" type="error" dense class="mb-4">
                {{ fetchError }}
            </v-alert>

            <v-card elevation="10" v-if="!fetchError">
                <v-card-text>
                    <div class="v-row">
                        <div class="v-col-md-8 v-col-12">
                            <div class="d-flex align-center">
                                <div>
                                    <h3 class="card-title mb-1">ตารางเซ็นสัญญาเปรียบเทียบตามพื้นที่ ประจำปี {{
                                        selectedYear }} -
                                        <span v-if="selectedQuarter && !selectedMonth">{{ selectedQuarter }}</span>
                                        <span v-if="selectedMonth && !selectedQuarter">เดือน {{ selectedMonth
                                        }}</span>
                                        <span v-if="!selectedQuarter && !selectedMonth">ทั้งปี</span>
                                    </h3>
                                    <h5 class="card-subtitle" style="text-align: left;">(หน่วย : ล้านบาท)</h5>
                                </div>
                            </div>
                        </div>

                        <div class="v-col-md-4 v-col-12 text-right">
                            <div class="d-flex  justify-end v-col-md-12 v-col-lg-12 v-col-12 ">

                                <v-btn class=" text-primary   v-btn--size-small " @click="exportToExcel">
                                    <div class="text-none font-weight-regular muted">Export to CSV</div>
                                </v-btn>
                            </div>
                        </div>
                    </div>
                    <br><br>

                    <v-table>

                        <thead style="background-color: #F5F5F5;">
                            <tr>
                                <th class="text-h6">Data by Region</th>
                                <th class="text-h6" :colspan="4"
                                    style="text-align: center;  border-bottom: 2px solid #00A6D4;">
                                    ประจำปี {{ selectedYear }} -
                                    <span v-if="selectedQuarter && !selectedMonth">{{ selectedQuarter }}</span>
                                    <span v-if="selectedMonth && !selectedQuarter">เดือน {{ selectedMonth }}</span>
                                    <span v-if="!selectedQuarter && !selectedMonth">ทั้งปี</span>
                                </th>
                            </tr>
                            <tr style="background-color: #F5F5F5;">
                                <th class="text-subtitle-1">พื้นที่</th>
                                <th style="text-align: center;">จำนวนหลัง</th>
                                <th style="text-align: center;">มูลค่ารวม</th>
                                <th style="text-align: center;">พื้นที่ใช้สอย</th>
                                <th style="text-align: center;">ราคาเฉลี่ย/ตร.ม.</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="region in regions" :key="region">
                                <td style="text-align: left;">
                                    <h6 class="text-subtitle-1">{{ region }}</h6>
                                </td>
                                <td>
                                    <h6 class="text-subtitle-1">{{ getRegionField(region,
                                        'unit').toLocaleString('th-TH') }}</h6>
                                </td>
                                <td>
                                    <h6 class="text-subtitle-1">{{ getRegionField(region,
                                        'total_value').toLocaleString('th-TH') }}</h6>
                                </td>
                                <td>
                                    <h6 class="text-subtitle-1">{{ getRegionField(region,
                                        'usable_area').toLocaleString('th-TH') }}</h6>
                                </td>
                                <td>
                                    <h6 class="text-subtitle-1">
                                        {{ formatNumber(getRegionField(region, 'price_per_sqm'), true) }}

                                    </h6>

                                </td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card-text>
            </v-card>
        </v-col>

        <v-col cols="12" sm="12" lg="12">
            <div class="v-row">
                <div class="v-col-sm-0 v-col-lg-3 v-col-0 py-0 mb-0" revenuecard="[object Object]">
                    <div
                        class="v-card v-theme--BLUE_THEME v-card--density-default elevation-10 rounded-md v-card--variant-elevated">
                        <div class="v-card__loader">
                            <div class="v-progress-linear v-theme--BLUE_THEME v-locale--is-ltr" role="progressbar"
                                aria-hidden="true" aria-valuemin="0" aria-valuemax="100"
                                style="top: 0px; height: 0px; --v-progress-linear-height: 2px;">
                                <div class="v-progress-linear__background"></div>
                                <div class="v-progress-linear__buffer" style="width: 0%;"></div>
                                <div class="v-progress-linear__indeterminate">
                                    <div class="v-progress-linear__indeterminate long"></div>
                                    <div class="v-progress-linear__indeterminate short"></div>
                                </div>
                            </div>
                        </div>
                        <div class="v-card-text pa-5">
                            <div class="d-flex align-center ga-4"><button type="button"
                                    class="v-btn v-btn--elevated v-btn--icon v-theme--BLUE_THEME v-btn--density-default v-btn--size-default v-btn--variant-elevated bg-primary elevation-0"
                                    dark=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24">
                                        <path fill="currentColor"
                                            d="M11.25 18a.75.75 0 0 0 1.5 0v-3a.75.75 0 0 0-1.5 0z" />
                                        <path fill="currentColor" fill-rule="evenodd"
                                            d="M12 1.25c-.708 0-1.351.203-2.05.542c-.674.328-1.454.812-2.427 1.416L5.456 4.491c-.92.572-1.659 1.03-2.227 1.465c-.589.45-1.041.91-1.368 1.507c-.326.595-.472 1.229-.543 1.978c-.068.725-.068 1.613-.068 2.726v1.613c0 1.904 0 3.407.153 4.582c.156 1.205.486 2.178 1.23 2.947c.747.773 1.697 1.119 2.875 1.282c1.14.159 2.598.159 4.434.159h4.116c1.836 0 3.294 0 4.434-.159c1.177-.163 2.128-.509 2.876-1.282c.743-.769 1.073-1.742 1.23-2.947c.152-1.175.152-2.678.152-4.582v-1.613c0-1.113 0-2-.068-2.726c-.07-.75-.217-1.383-.543-1.978c-.327-.597-.78-1.056-1.368-1.507c-.568-.436-1.306-.893-2.227-1.465l-2.067-1.283c-.973-.604-1.753-1.088-2.428-1.416c-.697-.34-1.34-.542-2.049-.542M8.28 4.504c1.015-.63 1.73-1.072 2.327-1.363c.581-.283.993-.391 1.393-.391s.812.108 1.393.391c.598.29 1.312.733 2.327 1.363l2 1.241c.961.597 1.636 1.016 2.14 1.402c.489.375.77.684.963 1.036c.193.353.306.766.365 1.398c.061.648.062 1.465.062 2.623v1.521c0 1.97-.002 3.376-.14 4.443c-.136 1.048-.393 1.656-.82 2.099c-.425.439-1.003.7-2.004.839c-1.026.142-2.379.144-4.286.144h-4c-1.908 0-3.26-.002-4.286-.144c-1.001-.14-1.579-.4-2.003-.84c-.428-.442-.685-1.05-.82-2.098c-.14-1.067-.141-2.472-.141-4.443v-1.521c0-1.158 0-1.975.062-2.623c.059-.632.172-1.045.365-1.398c.193-.352.474-.661.964-1.036c.503-.386 1.178-.805 2.139-1.402z"
                                            clip-rule="evenodd" />
                                    </svg></button>
                                <div class="">
                                    <h2 class="text-h4">{{
                                        totalUnit.toLocaleString('th-TH') }}</h2>
                                    <p class="textSecondary mt-1 text-15">จำนวนหลัง</p>
                                </div>
                            </div>
                        </div><!----><!----><span class="v-card__underlay"></span>
                    </div>
                </div>
                <div class="v-col-sm-6 v-col-lg-3 v-col-12 py-0 mb-3" revenuecard="[object Object]">
                    <div
                        class="v-card v-theme--BLUE_THEME v-card--density-default elevation-10 rounded-md v-card--variant-elevated">
                        <!---->
                        <div class="v-card__loader">
                            <div class="v-progress-linear v-theme--BLUE_THEME v-locale--is-ltr" role="progressbar"
                                aria-hidden="true" aria-valuemin="0" aria-valuemax="100"
                                style="top: 0px; height: 0px; --v-progress-linear-height: 2px;"><!---->
                                <div class="v-progress-linear__background"></div>
                                <div class="v-progress-linear__buffer" style="width: 0%;"></div>
                                <div class="v-progress-linear__indeterminate">
                                    <div class="v-progress-linear__indeterminate long"></div>
                                    <div class="v-progress-linear__indeterminate short"></div>
                                </div><!---->
                            </div>
                        </div><!----><!---->
                        <div class="v-card-text pa-5">
                            <div class="d-flex align-center ga-4"><button type="button"
                                    class="v-btn v-btn--elevated v-btn--icon v-theme--BLUE_THEME v-btn--density-default v-btn--size-default v-btn--variant-elevated bg-secondary elevation-0"
                                    dark=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24">
                                        <g fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path
                                                d="M2 14c0-3.771 0-5.657 1.172-6.828S6.229 6 10 6h4c3.771 0 5.657 0 6.828 1.172S22 10.229 22 14s0 5.657-1.172 6.828S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.172S2 17.771 2 14Zm14-8c0-1.886 0-2.828-.586-3.414S13.886 2 12 2s-2.828 0-3.414.586S8 4.114 8 6" />
                                            <path stroke-linecap="round"
                                                d="M12 17.333c1.105 0 2-.746 2-1.666S13.105 14 12 14s-2-.746-2-1.667c0-.92.895-1.666 2-1.666m0 6.666c-1.105 0-2-.746-2-1.666m2 1.666V18m0-8v.667m0 0c1.105 0 2 .746 2 1.666" />
                                        </g>
                                    </svg></button>
                                <div class="">
                                    <h2 class="text-h4">{{
                                        totalValue.toLocaleString('th-TH') }}</h2>
                                    <p class="textSecondary mt-1 text-15">มูลค่ารวม</p>
                                </div>
                            </div>
                        </div><!----><!----><span class="v-card__underlay"></span>
                    </div>
                </div>
                <div class="v-col-sm-6 v-col-lg-3 v-col-12 py-0 mb-3" revenuecard="[object Object]">
                    <div
                        class="v-card v-theme--BLUE_THEME v-card--density-default elevation-10 rounded-md v-card--variant-elevated">
                        <!---->
                        <div class="v-card__loader">
                            <div class="v-progress-linear v-theme--BLUE_THEME v-locale--is-ltr" role="progressbar"
                                aria-hidden="true" aria-valuemin="0" aria-valuemax="100"
                                style="top: 0px; height: 0px; --v-progress-linear-height: 2px;"><!---->
                                <div class="v-progress-linear__background"></div>
                                <div class="v-progress-linear__buffer" style="width: 0%;"></div>
                                <div class="v-progress-linear__indeterminate">
                                    <div class="v-progress-linear__indeterminate long"></div>
                                    <div class="v-progress-linear__indeterminate short"></div>
                                </div><!---->
                            </div>
                        </div><!----><!---->
                        <div class="v-card-text pa-5">
                            <div class="d-flex align-center ga-4"><button type="button"
                                    class="v-btn v-btn--elevated v-btn--icon v-theme--BLUE_THEME v-btn--density-default v-btn--size-default v-btn--variant-elevated bg-error elevation-0"
                                    dark=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24">
                                        <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5">
                                            <path
                                                d="M11 2c-4.055.007-6.178.107-7.536 1.464C2 4.928 2 7.285 2 11.999s0 7.071 1.464 8.536C4.93 21.999 7.286 21.999 12 21.999s7.071 0 8.535-1.464c1.358-1.357 1.457-3.48 1.464-7.536" />
                                            <path stroke-linejoin="round"
                                                d="m13 11l9-9m0 0h-5.344M22 2v5.344M21 3l-9 9m0 0h4m-4 0V8" />
                                        </g>
                                    </svg></button>
                                <div class="">
                                    <h2 class="text-h4">{{
                                        totalArea.toLocaleString('th-TH') }}</h2>
                                    <p class="textSecondary mt-1 text-15">พื้นที่ใช้สอย</p>
                                </div>
                            </div>
                        </div><!----><!----><span class="v-card__underlay"></span>
                    </div>
                </div>
                <div class="v-col-sm-6 v-col-lg-3 v-col-12 py-0 mb-3" revenuecard="[object Object]">
                    <div
                        class="v-card v-theme--BLUE_THEME v-card--density-default elevation-10 rounded-md v-card--variant-elevated">
                        <!---->
                        <div class="v-card__loader">
                            <div class="v-progress-linear v-theme--BLUE_THEME v-locale--is-ltr" role="progressbar"
                                aria-hidden="true" aria-valuemin="0" aria-valuemax="100"
                                style="top: 0px; height: 0px; --v-progress-linear-height: 2px;"><!---->
                                <div class="v-progress-linear__background"></div>
                                <div class="v-progress-linear__buffer" style="width: 0%;"></div>
                                <div class="v-progress-linear__indeterminate">
                                    <div class="v-progress-linear__indeterminate long"></div>
                                    <div class="v-progress-linear__indeterminate short"></div>
                                </div><!---->
                            </div>
                        </div><!----><!---->
                        <div class="v-card-text pa-5">
                            <div class="d-flex align-center ga-4"><button type="button"
                                    class="v-btn v-btn--elevated v-btn--icon v-theme--BLUE_THEME v-btn--density-default v-btn--size-default v-btn--variant-elevated bg-warning elevation-0"
                                    dark=""> <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24">
                                        <g fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path
                                                d="M4.979 9.685C2.993 8.891 2 8.494 2 8s.993-.89 2.979-1.685l2.808-1.123C9.773 4.397 10.767 4 12 4s2.227.397 4.213 1.192l2.808 1.123C21.007 7.109 22 7.506 22 8s-.993.89-2.979 1.685l-2.808 1.124C14.227 11.603 13.233 12 12 12s-2.227-.397-4.213-1.191z" />
                                            <path
                                                d="m5.766 10l-.787.315C2.993 11.109 2 11.507 2 12s.993.89 2.979 1.685l2.808 1.124C9.773 15.603 10.767 16 12 16s2.227-.397 4.213-1.191l2.808-1.124C21.007 12.891 22 12.493 22 12s-.993-.89-2.979-1.685L18.234 10" />
                                            <path
                                                d="m5.766 14l-.787.315C2.993 15.109 2 15.507 2 16s.993.89 2.979 1.685l2.808 1.124C9.773 19.603 10.767 20 12 20s2.227-.397 4.213-1.192l2.808-1.123C21.007 16.891 22 16.494 22 16c0-.493-.993-.89-2.979-1.685L18.234 14" />
                                        </g>
                                    </svg></button>
                                <div class="">
                                    <h2 class="text-h4">
                                        {{ averagePricePerSqm.toLocaleString('th-TH', {
                                            minimumFractionDigits: 2,
                                        maximumFractionDigits: 2 }) }}
                                    </h2>
                                    <p class="textSecondary mt-1 text-15">ราคาเฉลี่ย/ตร.ม.</p>
                                </div>
                            </div>
                        </div> <span class="v-card__underlay"></span>
                    </div>
                </div>
            </div>
        </v-col>

    </v-row>
</template>


<style scoped>
th,
td {
    text-align: center;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.5s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
