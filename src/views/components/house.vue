<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import * as XLSX from 'xlsx';
import ExcelJS from 'exceljs';
import type { BorderStyle, Cell } from 'exceljs';


const currentYear = new Date().getFullYear() + 543;
const selectedYear = ref(currentYear.toString());
const yearOptions = computed(() => {
    return Array.from({ length: 7 }, (_, i) => (currentYear - i).toString());
});

const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user';

const summaryData = ref<Record<string, Record<string, number>>>({});

const priceRanges = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป'];
const dataTypes = ['unit', 'total_value', 'usable_area', 'price_per_sqm'];

const selectedQuarter = ref('');
const selectedMonth = ref('');
const quarterOptions = ['ไตรมาสที่ 1', 'ไตรมาสที่ 2', 'ไตรมาสที่ 3', 'ไตรมาสที่ 4'];

const chartSeries = ref<any[]>([]);

interface SummaryData {
    unit: Record<string, number>;
    total_value: Record<string, number>;
    usable_area: Record<string, number>;
    price_per_sqm: Record<string, number>;
}

const getTotal = (type: string) => {
    const ranges = priceRanges;
    return ranges.reduce((total, range) => {
        return total + (summaryData.value?.[type]?.[range] || 0);
    }, 0);
};

const monthMap: { [key: string]: number } = {
    มกราคม: 1,
    กุมภาพันธ์: 2,
    มีนาคม: 3,
    เมษายน: 4,
    พฤษภาคม: 5,
    มิถุนายน: 6,
    กรกฎาคม: 7,
    สิงหาคม: 8,
    กันยายน: 9,
    ตุลาคม: 10,
    พฤศจิกายน: 11,
    ธันวาคม: 12
};

const monthOptions = Object.keys(monthMap);

const chartOptions = ref({
    chart: {
        height: 350,
        type: 'line',
        stacked: false,
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
        toolbar: {
            show: true,
            tools: {
                download: true
            }
        }
    },
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '50%',
            dataLabels: {
                position: 'top',
                offsetY: 0
            }
        },
        line: {
            dataLabels: {
                position: 'top',
                offsetY: 0
            },
            curve: 'smooth'
        }
    },
    dataLabels: {
        enabled: true,
        position: 'top',
        offsetY: -13,
        style: {
            fontSize: '10px'
        },
        formatter: (value: number) => {
            return value >= 1000 ? value.toLocaleString('th-TH') : value.toString();
        }
    },
    stroke: {
        width: [2, 2, 4],
        curve: 'smooth'
    },
    grid: {
        show: true,
        strokeDashArray: 4,
        borderColor: 'rgba(0, 0, 0, 0.1)'
    },
    xaxis: {
        categories: ['ไม่เกิน 2.50 ล้านบ้าน', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20 ล้านบาท'],
        labels: {
            style: {
                fontSize: '12px',
                colors: '#6c757d'
            }
        }
    },
    yaxis: [
        {
            seriesName: 'ปี 2567',
            axisTicks: { show: true },
            labels: {
                show: false,
                style: { colors: '#008FFB' }
            },
            tooltip: { enabled: true }
        },
        {
            seriesName: 'ปี 2568',
            opposite: true,
            axisTicks: { show: true },
            labels: {
                show: false,
                style: { colors: '#00E396' }
            }
        },
        {
            seriesName: 'เปอร์เซ็นต์การเปลี่ยนแปลง',
            opposite: false,
            axisTicks: { show: true },
            labels: {
                show: false,
                style: { colors: '#FF4560' }
            }
        }
    ],
    tooltip: {
        fixed: {
            enabled: true,
            position: 'topLeft',
            offsetY: 0,
            offsetX: 0
        }
    },
    legend: {
        horizontalAlign: 'center',
        offsetX: 0
    }
});

const categoryOrder = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป'];

const updateChartData = (data: SummaryData) => {
    const categories = categoryOrder;

    const unitData = categories.map((category) => data.unit[category] || 0);
    const totalValueData = categories.map((category) => data.total_value[category] || 0);
    const pricePerSqmData = categories.map((category) => data.price_per_sqm[category] || 0);
    const usableAreaData = categories.map((category) => data.usable_area[category] || 0);

    chartSeries.value = [
        {
            name: 'จำนวนหลัง',
            type: 'column',
            data: unitData
        },
        {
            name: 'มูลค่ารวม',
            type: 'column',
            data: totalValueData
        },
        {
            name: 'พื้นที่ใช้สอย',
            type: 'line',
            data: usableAreaData
        }
    ];

    chartOptions.value.xaxis.categories = categories;
};

const fetchSummary = async () => {
    if (!userId && userRole !== 'admin') return;

    try {
        const res = await fetch('https://uat.hba-sales.org/backend/get_contract_summary_main.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: userId,
                buddhist_year: selectedYear.value,
                quarter: selectedQuarter.value || null,
                month: monthMap[selectedMonth.value] || null,
                role: userRole
            })
        });

        if (!res.ok) {
            throw new Error(`HTTP error! Status: ${res.status}`);
        }

        const data = await res.json();
        summaryData.value = data;
        console.log('✅ ข้อมูลที่ได้จาก backend:', data);

        updateChartData(data);
    } catch (err) {
        console.error('❌ Error fetching summary:', err);
    }
};

watch([selectedYear, selectedQuarter, selectedMonth], fetchSummary, { immediate: true });
onMounted(fetchSummary);

const getValue = (type: string, range: string) => {
    return summaryData.value?.[type]?.[range] || 0;
};

const exportToExcel = async () => {
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Contract Summary');

    // Add title row
    worksheet.addRow([`สรุปข้อมูลรายงานแบ่งตามมูลค่า ประจำปี ${selectedYear.value}`]);
    worksheet.getRow(1).font = { bold: true, size: 14 };
    worksheet.getRow(1).alignment = { horizontal: 'center' };
    worksheet.mergeCells('A1:E1');  // Merge title row

    // Add header row with column names
    const headerRow = ['Price Range', 'จำนวนหลัง', 'มูลค่ารวม', 'พื้นที่ใช้สอย', 'ราคาเฉลี่ย/ตร.ม.'];
    const headerRowFormatted = worksheet.addRow(headerRow);
    headerRowFormatted.font = { bold: true };
    headerRowFormatted.alignment = { horizontal: 'center' };

    // Apply background color to the header row (light grey)
    headerRowFormatted.eachCell((cell: Cell) => {
        cell.fill = {
            type: 'pattern',
            pattern: 'solid',
            fgColor: { argb: 'D3D3D3' } // Light grey background
        };
        // Apply borders to the header row
        cell.border = {
            top: { style: 'thin' as BorderStyle },
            left: { style: 'thin' as BorderStyle },
            right: { style: 'thin' as BorderStyle },
            bottom: { style: 'thin' as BorderStyle }
        };
    });

    // Add data rows
    priceRanges.forEach((range) => {
        const row = [
            range,
            getValue('unit', range),
            getValue('total_value', range),
            getValue('usable_area', range),
            getValue('price_per_sqm', range)
        ];
        const dataRow = worksheet.addRow(row);

        // Apply number formatting (optional)
        dataRow.getCell(2).numFmt = '#,##0';  // Format number for 'จำนวนหลัง'
        dataRow.getCell(3).numFmt = '#,##0.00';  // Format number for 'มูลค่ารวม'
        dataRow.getCell(4).numFmt = '#,##0.00';  // Format number for 'พื้นที่ใช้สอย'
        dataRow.getCell(5).numFmt = '#,##0.00';  // Format number for 'ราคาเฉลี่ย/ตร.ม.'

        // Apply background color for the data row (light blue)
        dataRow.eachCell((cell: Cell) => {
            cell.fill = {
                type: 'pattern',
                pattern: 'solid',
                fgColor: { argb: 'D9EAF7' } // Light blue background
            };

            // Apply borders for data rows
            cell.border = {
                top: { style: 'thin' as BorderStyle },
                left: { style: 'thin' as BorderStyle },
                right: { style: 'thin' as BorderStyle },
                bottom: { style: 'thin' as BorderStyle }
            };
        });
    });

    // Set column widths
    worksheet.columns = [
        { header: 'Price Range', key: 'price_range', width: 25 },
        { header: 'จำนวนหลัง', key: 'unit', width: 15 },
        { header: 'มูลค่ารวม', key: 'total_value', width: 20 },
        { header: 'พื้นที่ใช้สอย', key: 'usable_area', width: 20 },
        { header: 'ราคาเฉลี่ย/ตร.ม.', key: 'price_per_sqm', width: 20 }
    ];

    // Write Excel file to disk
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'contract_summary_report.xlsx';
    a.click();
    window.URL.revokeObjectURL(url);
};

</script>

<template>
    <v-row>
        <v-col cols="12" sm="12" lg="12">
            <div class="mt-3 mb-6">
                <div class="d-flex justify-space-between">
                    <div class="d-flex py-0 align-center">
                        <div>
                            <h3 class="text-h5 card-title">รายงานยอดเซ็นสัญญาแบ่งตามมูลค่าบ้าน</h3>
                            <ul class="v-breadcrumbs v-breadcrumbs--density-default text-subtitle-1 textSecondary pa-0 ml-n1">
                                <!---->
                                <li class="v-breadcrumbs-item" text="Home">
                                    <a class="v-breadcrumbs-item--link" href="#">
                                        <p>หน้าแรก</p>
                                    </a>
                                </li>
                                <li class="v-breadcrumbs-divider">
                                    <i
                                        class="mdi-chevron-right mdi v-icon notranslate v-theme--BLUE_THEME"
                                        aria-hidden="true"
                                        style="font-size: 15px; height: 15px; width: 15px"
                                    ></i>
                                </li>
                                <li class="v-breadcrumbs-item" text="Dashboard">
                                    <a class="v-breadcrumbs-item--link" href="#">
                                        <p>รายงานยอดเซ็นสัญญาแบ่งตามมูลค่าบ้าน</p>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div></div>
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

        <v-col cols="12">
            <VCard elevation="10">
                <v-card-text>
                    <div class="v-row">
                        <div class="v-col-md-8 v-col-12">
                            <div class="d-flex align-center">
                                <div>
                                    <h3 class="card-title mb-1">
                                        รายงานยอดแบ่งตามมูลค่าบ้าน ประจำปี {{ selectedYear }} -
                                        <span v-if="selectedQuarter && !selectedMonth">{{ selectedQuarter }}</span>
                                        <span v-if="selectedMonth && !selectedQuarter">เดือน {{ selectedMonth }}</span>
                                        <span v-if="!selectedQuarter && !selectedMonth">ทั้งปี</span>
                                    </h3>
                                    <h5 class="card-subtitle" style="text-align: left">(หน่วย : ล้านบาท)</h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5">
                        <apexchart type="line" :options="chartOptions" :series="chartSeries" height="350" />
                    </div>
                </v-card-text>
            </VCard>
        </v-col>

        <v-col cols="12" sm="12" lg="12">
            <v-card elevation="10">
                <v-card-text>
                    <div class="v-row">
                        <div class="v-col-md-8 v-col-12">
                            <div class="d-flex align-center">
                                <div>
                                    <h3 class="card-title mb-1">
                                        ตารางสรุปยอดแบ่งตามมูลค่าบ้าน ประจำปี {{ selectedYear }} -
                                        <span v-if="selectedQuarter && !selectedMonth">{{ selectedQuarter }}</span>
                                        <span v-if="selectedMonth && !selectedQuarter">เดือน {{ selectedMonth }}</span>
                                        <span v-if="!selectedQuarter && !selectedMonth">ทั้งปี</span>
                                    </h3>
                                    <h5 class="card-subtitle" style="text-align: left">(หน่วย : ล้านบาท)</h5>
                                </div>
                            </div>
                        </div>

                        <div class="v-col-md-4 v-col-12 text-right">
                            <div class="d-flex justify-end v-col-md-12 v-col-lg-12 v-col-12">
                                <v-btn class="text-primary v-btn--size-small" @click="exportToExcel">
                                    <div class="text-none font-weight-regular muted">Export to CSV</div>
                                </v-btn>
                            </div>
                        </div>
                    </div>
                    <br /><br />
                    <div class="v-table v-theme--BLUE_THEME v-table--density-default">
                        <div class="v-table__wrapper">
                            <table>
                                <thead style="background-color: #f5f5f5">
                                    <tr>
                                        <th class="text-h6">Summary of value</th>
                                        <th
                                            class="text-h6"
                                            :colspan="priceRanges.length"
                                            style="text-align: center; border-bottom: 2px solid #00a6d4"
                                        >
                                            ประจำปี {{ selectedYear }} -
                                            <span v-if="selectedQuarter && !selectedMonth">{{ selectedQuarter }}</span>
                                            <span v-if="selectedMonth && !selectedQuarter">เดือน {{ selectedMonth }}</span>
                                            <span v-if="!selectedQuarter && !selectedMonth">ทั้งปี</span>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th class="text-subtitle-1" style="font-weight: 400">(Unit: THB million)</th>
                                        <th v-for="range in priceRanges" :key="range" class="text-p" style="font-size: 14px">
                                            {{ range }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="type in dataTypes" :key="type">
                                        <td>
                                            <h6 class="text-subtitle-1">
                                                {{
                                                    type === 'unit'
                                                        ? 'จำนวนหลัง'
                                                        : type === 'total_value'
                                                          ? 'มูลค่ารวม'
                                                          : type === 'usable_area'
                                                            ? 'พื้นที่ใช้สอย'
                                                            : type === 'price_per_sqm'
                                                              ? 'ราคาเฉลี่ย/ตร.ม.'
                                                              : type
                                                }}
                                            </h6>
                                        </td>
                                        <td v-for="range in priceRanges" :key="type + '-' + range">
                                            <h6 class="text-subtitle-1">{{ getValue(type, range).toLocaleString('th-TH') }}</h6>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <br /><br />
                </v-card-text>
            </v-card>
        </v-col>

        <v-col cols="12" sm="12" lg="12">
            <div class="v-row">
                <div
                    v-for="(label, index) in ['จำนวนหลัง', 'มูลค่ารวม', 'พื้นที่ใช้สอย', 'ราคาเฉลี่ย/ตร.ม.']"
                    :key="index"
                    class="v-col-sm-6 v-col-lg-3 v-col-12 py-0 mb-3"
                >
                    <div class="v-card v-theme--BLUE_THEME v-card--density-default elevation-10 rounded-md v-card--variant-elevated">
                        <div class="v-card-text pa-5">
                            <div class="d-flex align-center ga-4">
                                <button
                                    type="button"
                                    class="v-btn v-btn--elevated v-btn--icon v-theme--BLUE_THEME v-btn--density-default v-btn--size-default v-btn--variant-elevated"
                                    :class="{
                                        'bg-primary': label === 'จำนวนหลัง',
                                        'bg-secondary': label === 'มูลค่ารวม',
                                        'bg-error': label === 'พื้นที่ใช้สอย',
                                        'bg-warning': label === 'ราคาเฉลี่ย/ตร.ม.'
                                    }"
                                    dark
                                >
                                    <svg
                                        v-if="label === 'จำนวนหลัง'"
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="24"
                                        height="24"
                                        viewBox="0 0 24 24"
                                    >
                                        <g fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path
                                                d="M2 12.204c0-2.289 0-3.433.52-4.381c.518-.949 1.467-1.537 3.364-2.715l2-1.241C9.889 2.622 10.892 2 12 2s2.11.622 4.116 1.867l2 1.241c1.897 1.178 2.846 1.766 3.365 2.715S22 9.915 22 12.203v1.522c0 3.9 0 5.851-1.172 7.063S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.212S2 17.626 2 13.725z"
                                            />
                                            <path stroke-linecap="round" d="M12 15v3" />
                                        </g>
                                    </svg>
                                    <svg
                                        v-else-if="label === 'มูลค่ารวม'"
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="24"
                                        height="24"
                                        viewBox="0 0 24 24"
                                    >
                                        <g fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path
                                                d="M2 14c0-3.771 0-5.657 1.172-6.828S6.229 6 10 6h4c3.771 0 5.657 0 6.828 1.172S22 10.229 22 14s0 5.657-1.172 6.828S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.172S2 17.771 2 14Zm14-8c0-1.886 0-2.828-.586-3.414S13.886 2 12 2s-2.828 0-3.414.586S8 4.114 8 6"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                d="M12 17.333c1.105 0 2-.746 2-1.666S13.105 14 12 14s-2-.746-2-1.667c0-.92.895-1.666 2-1.666m0 6.666c-1.105 0-2-.746-2-1.666m2 1.666V18m0-8v.667m0 0c1.105 0 2 .746 2 1.666"
                                            />
                                        </g>
                                    </svg>

                                    <svg
                                        v-else-if="label === 'พื้นที่ใช้สอย'"
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="24"
                                        height="24"
                                        viewBox="0 0 24 24"
                                    >
                                        <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5">
                                            <path
                                                d="M11 2c-4.055.007-6.178.107-7.536 1.464C2 4.928 2 7.285 2 11.999s0 7.071 1.464 8.536C4.93 21.999 7.286 21.999 12 21.999s7.071 0 8.535-1.464c1.358-1.357 1.457-3.48 1.464-7.536"
                                            />
                                            <path stroke-linejoin="round" d="m13 11l9-9m0 0h-5.344M22 2v5.344M21 3l-9 9m0 0h4m-4 0V8" />
                                        </g>
                                    </svg>

                                    <svg
                                        v-else-if="label === 'ราคาเฉลี่ย/ตร.ม.'"
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="24"
                                        height="24"
                                        viewBox="0 0 24 24"
                                    >
                                        <g fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path
                                                d="M4.979 9.685C2.993 8.891 2 8.494 2 8s.993-.89 2.979-1.685l2.808-1.123C9.773 4.397 10.767 4 12 4s2.227.397 4.213 1.192l2.808 1.123C21.007 7.109 22 7.506 22 8s-.993.89-2.979 1.685l-2.808 1.124C14.227 11.603 13.233 12 12 12s-2.227-.397-4.213-1.191z"
                                            />
                                            <path
                                                d="m5.766 10l-.787.315C2.993 11.109 2 11.507 2 12s.993.89 2.979 1.685l2.808 1.124C9.773 15.603 10.767 16 12 16s2.227-.397 4.213-1.191l2.808-1.124C21.007 12.891 22 12.493 22 12s-.993-.89-2.979-1.685L18.234 10"
                                            />
                                            <path
                                                d="m5.766 14l-.787.315C2.993 15.109 2 15.507 2 16s.993.89 2.979 1.685l2.808 1.124C9.773 19.603 10.767 20 12 20s2.227-.397 4.213-1.192l2.808-1.123C21.007 16.891 22 16.494 22 16c0-.493-.993-.89-2.979-1.685L18.234 14"
                                            />
                                        </g>
                                    </svg>
                                </button>
                                <div class="">
                                    <h2 class="text-h4">{{ getTotal(dataTypes[index]).toLocaleString('th-TH') }}</h2>

                                    <p class="textSecondary mt-1 text-15">{{ label }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </v-col>
    </v-row>
</template>

<style scoped>
.text-subtitle-1 {
    font-size: 14px;
}
</style>
