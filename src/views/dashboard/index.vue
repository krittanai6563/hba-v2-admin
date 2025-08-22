<script setup lang="ts">
import SalesOverview from '@/components/dashboard/SalesOverview.vue';
import TheActivityTimeline from '@/components/dashboard/TheActivityTimeline.vue';
import TotalSales from '@/components/dashboard/TotalSales.vue';
import { ref, computed, watch, onMounted } from 'vue';

const selectedYear = ref((new Date().getFullYear() + 543).toString());
const selectedQuarter = ref('ทั้งหมด');

const statusMessage = ref('');
const contractValue = ref(0);

const fetchErrorUserStatus = ref('');
const fetchErrorRegionSummary = ref('');


const yearOptions = computed(() => {
    const current = new Date().getFullYear() + 543;
    return Array.from({ length: 6 }, (_, i) => (current - i).toString());
});

const userId = localStorage.getItem('user_id');


const userRole = ref(localStorage.getItem('user_role') || 'user');
const isAdmin = computed(() => userRole.value === 'admin' || userRole.value === 'master');



const buildPayload = () => {
    const payload: any = {
        buddhist_year: selectedYear.value,
        role: userRole.value,
    };
    if (!isAdmin.value && userId) {
        payload.user_id = userId;
    }
    if (selectedQuarter.value !== 'ทั้งหมด') {
        payload.quarter = selectedQuarter.value;
    }
    return payload;
};

const regionData = ref<Record<string, { unit: number; total_value: number; usable_area: number }>>({});
const fetchError = ref('');
const latestMonthValue = ref(0);

const fetchRegionSummary = async () => {
    if (!selectedYear.value) return;

    const payload = buildPayload();
    fetchErrorRegionSummary.value = '';

    try {
        const res = await fetch('https://d2e03fa78899.ngrok-free.app/package/backend/file.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (data.error) {
            fetchErrorRegionSummary.value = `เกิดข้อผิดพลาด: ${data.error}`;
            regionData.value = {};
            latestMonthValue.value = 0;
        } else {
            regionData.value = data.regions || {};
            latestMonthValue.value = data.latest_month_value || 0;
        }
    } catch (err: any) {
        console.error('Error fetching region data:', err);
        fetchErrorRegionSummary.value = 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้';
    }
};

watch(selectedYear, fetchRegionSummary, { immediate: true });
watch(selectedQuarter, fetchRegionSummary);

type RegionField = 'unit' | 'total_value' | 'usable_area' | 'price_per_sqm';

const regions = [
    'กรุงเทพปริมณฑล',
    'ภาคเหนือ',
    'ภาคตะวันออกเฉียงเหนือ',
    'ภาคกลาง',
    'ภาคตะวันออก',
    'ภาคใต้',
    'ภาคตะวันตก'
];

const getRegionField = (region: string, field: RegionField): number => {
    const entry = regionData.value[region];
    if (!entry) return 0;

    return field === 'price_per_sqm'
        ? entry.usable_area > 0
            ? Math.round(entry.total_value / entry.usable_area)
            : 0
        : entry[field];
};

const chartSeries = computed(() => [
    {
        name: 'จำนวนหลัง',
        data: regions.map(region => getRegionField(region, 'unit'))
    },
    {
        name: 'พื้นที่ใช้สอย',
        data: regions.map(region => getRegionField(region, 'usable_area'))
    },
    {
        name: 'มูลค่ารวม',
        data: regions.map(region => getRegionField(region, 'total_value'))
    }
]);

const totalUnit = computed(() =>
    regions.reduce((sum, region) => sum + getRegionField(region, 'unit'), 0)
);

const totalValue = computed(() =>
    regions.reduce((sum, region) => sum + getRegionField(region, 'total_value'), 0)
);

const totalArea = computed(() =>
    regions.reduce((sum, region) => sum + getRegionField(region, 'usable_area'), 0)
);

const averagePricePerSqm = computed(() => {
    const value = totalValue.value;
    const area = totalArea.value;
    return area > 0 ? (value / area).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00';
});


const formatNumber = (value: number, isDecimal = false) => {
    return value.toLocaleString('th-TH', {
        minimumFractionDigits: isDecimal ? 2 : 0,
        maximumFractionDigits: isDecimal ? 2 : 0
    });
};


defineExpose({
    formatNumber,
    totalUnit,
    totalValue,
    totalArea,
    averagePricePerSqm,
});

const months = [
    "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
    "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
];

// คำนวณชื่อเดือนและปี
const currentYear = new Date().getFullYear() + 543;  // ปีพุทธศักราช
const currentMonth = new Date().getMonth();  // เดือนปัจจุบัน
const currentMonthName = months[currentMonth];  

const errorMessage = computed(() => {
    return `กรุณากรอกข้อมูลก่อนวันที่ 10 ${currentMonthName} ${currentYear}`;
});

const fetchUserStatus = async () => {
    if (!userId) {
        fetchErrorUserStatus.value = 'ไม่พบข้อมูลผู้ใช้';
        return;
    }

    try {
        const payload = {
            user_id: userId,
            buddhist_year: currentYear.toString(),
            month_number: (currentMonth + 1).toString()  // เดือนปัจจุบัน
        };

        const res = await fetch('http://localhost/package/backend/data_and_email.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });

        const data = await res.json();

        if (data.error) {
            fetchErrorUserStatus.value = data.error;
        } else {
            statusMessage.value = data.status;
        }
    } catch (err) {
        console.error('Error fetching user status:', err);
        fetchErrorUserStatus.value = 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้';
        console.log(err);
    }
};

onMounted(fetchUserStatus);

</script>





<template>
    <v-row v-if="fetchErrorUserStatus && userRole === 'user'">

        <v-alert density="compact" type="error" :text="errorMessage" title="กรุณากรอกข้อมูล"></v-alert>
    </v-row>

    <v-row v-if="statusMessage && userRole === 'user'">
        <v-alert density="compact" v-bind:type="statusMessage === 'กรอกข้อมูลเรียบร้อย' ? 'success' : 'error'"
            :text="statusMessage" title="สถานะการกรอกข้อมูล"></v-alert>
    </v-row>

    <v-row>
        <v-col cols="12" sm="12" lg="12">

            <div class="mt-3 mb-6">
                <div class="d-flex justify-space-between">
                    <div class="d-flex py-0 align-center">
                        <div>
                            <h3 class="text-h5 card-title">Dashboard</h3>
                            <ul
                                class="v-breadcrumbs v-breadcrumbs--density-default text-subtitle-1 textSecondary pa-0 ml-n1">
                                <!---->
                                <li class="v-breadcrumbs-item" text="Home"><a class="v-breadcrumbs-item--link" href="#">
                                        <p>Home</p>
                                    </a></li>
                                <li class="v-breadcrumbs-divider"><i
                                        class="mdi-chevron-right mdi v-icon notranslate v-theme--BLUE_THEME"
                                        aria-hidden="true" style="font-size: 15px; height: 15px; width: 15px;"></i></li>
                                <li class="v-breadcrumbs-item" text="Dashboard"><a class="v-breadcrumbs-item--link"
                                        href="#">
                                        <p>Dashboard</p>
                                    </a></li>
                            </ul>
                        </div>
                    </div>

                    <div>
                        <div class="d-sm-flex d-none ga-4 justify-content-end align-center">
                            <div class="d-flex ga-2">
                                <div>
                                    <p class="textSecondary text-13 mb-0 lh-normal">มูลค่ารวมเดือนล่าสุด</p>
                                    <h5 class="text-h5 text-primary mb-0 font-weight-medium mt-n1"> {{
                                        latestMonthValue.toLocaleString('th-TH') }} บาท</h5>


                                </div>
                                <div>
                                    <div class="vue-apexcharts" style="min-height: 40px;">
                                        <div id="apexchartsqycvbxyj"
                                            class="apexcharts-canvas apexchartsqycvbxyj apexcharts-theme-light"
                                            style="width: 70px; height: 40px;"><svg id="SvgjsSvg1543" width="70"
                                                height="40" xmlns="http://www.w3.org/2000/svg" version="1.1"
                                                xmlns:xlink="http://www.w3.org/1999/xlink"
                                                xmlns:svgjs="http://svgjs.dev" class="apexcharts-svg"
                                                xmlns:data="ApexChartsNS" transform="translate(0, 0)"
                                                style="background: transparent;">
                                                <foreignObject x="0" y="0" width="70" height="40">
                                                    <div class="apexcharts-legend" xmlns="http://www.w3.org/1999/xhtml"
                                                        style="max-height: 20px;"></div>
                                                </foreignObject>
                                                <g id="SvgjsG1596" class="apexcharts-yaxis" rel="0"
                                                    transform="translate(-18, 0)"></g>
                                                <g id="SvgjsG1545" class="apexcharts-inner apexcharts-graphical"
                                                    transform="translate(12.333333333333334, 0)">
                                                    <defs id="SvgjsDefs1544">
                                                        <linearGradient id="SvgjsLinearGradient1547" x1="0" y1="0"
                                                            x2="0" y2="1">
                                                            <stop id="SvgjsStop1548" stop-opacity="0.4"
                                                                stop-color="rgba(216,227,240,0.4)" offset="0"></stop>
                                                            <stop id="SvgjsStop1549" stop-opacity="0.5"
                                                                stop-color="rgba(190,209,230,0.5)" offset="1"></stop>
                                                            <stop id="SvgjsStop1550" stop-opacity="0.5"
                                                                stop-color="rgba(190,209,230,0.5)" offset="1"></stop>
                                                        </linearGradient>
                                                        <clipPath id="gridRectMaskqycvbxyj">
                                                            <rect id="SvgjsRect1552" width="74" height="44"
                                                                x="-10.333333333333334" y="-2" rx="0" ry="0" opacity="1"
                                                                stroke-width="0" stroke="none" stroke-dasharray="0"
                                                                fill="#fff"></rect>
                                                        </clipPath>
                                                        <clipPath id="forecastMaskqycvbxyj"></clipPath>
                                                        <clipPath id="nonForecastMaskqycvbxyj"></clipPath>
                                                        <clipPath id="gridRectMarkerMaskqycvbxyj">
                                                            <rect id="SvgjsRect1553" width="57.33333333333333"
                                                                height="44" x="-2" y="-2" rx="0" ry="0" opacity="1"
                                                                stroke-width="0" stroke="none" stroke-dasharray="0"
                                                                fill="#fff"></rect>
                                                        </clipPath>
                                                    </defs>
                                                    <rect id="SvgjsRect1551" width="3.809523809523809" height="40"
                                                        x="28.190513974144345" y="0" rx="0" ry="0" opacity="1"
                                                        stroke-width="0" stroke-dasharray="3"
                                                        fill="url(#SvgjsLinearGradient1547)"
                                                        class="apexcharts-xcrosshairs" y2="40" filter="none"
                                                        fill-opacity="0.9" x1="28.190513974144345"
                                                        x2="28.190513974144345"></rect>
                                                    <g id="SvgjsG1575" class="apexcharts-grid">
                                                        <g id="SvgjsG1576" class="apexcharts-gridlines-horizontal"
                                                            style="display: none;">
                                                            <line id="SvgjsLine1579" x1="-8.333333333333334" y1="0"
                                                                x2="61.666666666666664" y2="0" stroke="#e0e0e0"
                                                                stroke-dasharray="0" stroke-linecap="butt"
                                                                class="apexcharts-gridline"></line>
                                                            <line id="SvgjsLine1580" x1="-8.333333333333334" y1="10"
                                                                x2="61.666666666666664" y2="10" stroke="#e0e0e0"
                                                                stroke-dasharray="0" stroke-linecap="butt"
                                                                class="apexcharts-gridline"></line>
                                                            <line id="SvgjsLine1581" x1="-8.333333333333334" y1="20"
                                                                x2="61.666666666666664" y2="20" stroke="#e0e0e0"
                                                                stroke-dasharray="0" stroke-linecap="butt"
                                                                class="apexcharts-gridline"></line>
                                                            <line id="SvgjsLine1582" x1="-8.333333333333334" y1="30"
                                                                x2="61.666666666666664" y2="30" stroke="#e0e0e0"
                                                                stroke-dasharray="0" stroke-linecap="butt"
                                                                class="apexcharts-gridline"></line>
                                                            <line id="SvgjsLine1583" x1="-8.333333333333334" y1="40"
                                                                x2="61.666666666666664" y2="40" stroke="#e0e0e0"
                                                                stroke-dasharray="0" stroke-linecap="butt"
                                                                class="apexcharts-gridline"></line>
                                                        </g>
                                                        <g id="SvgjsG1577" class="apexcharts-gridlines-vertical"
                                                            style="display: none;"></g>
                                                        <line id="SvgjsLine1585" x1="0" y1="40" x2="53.33333333333333"
                                                            y2="40" stroke="transparent" stroke-dasharray="0"
                                                            stroke-linecap="butt"></line>
                                                        <line id="SvgjsLine1584" x1="0" y1="1" x2="0" y2="40"
                                                            stroke="transparent" stroke-dasharray="0"
                                                            stroke-linecap="butt"></line>
                                                    </g>
                                                    <g id="SvgjsG1578" class="apexcharts-grid-borders"
                                                        style="display: none;"></g>
                                                    <g id="SvgjsG1554"
                                                        class="apexcharts-bar-series apexcharts-plot-series">
                                                        <g id="SvgjsG1555" class="apexcharts-series" rel="1"
                                                            seriesName="" data:realIndex="0">
                                                            <path id="SvgjsPath1560"
                                                                d="M -1.9047619047619044 38.001 L -1.9047619047619044 32.001000000000005 C -1.9047619047619044 31.001000000000005 -0.9047619047619044 30.001 0.09523809523809557 30.001 L 0.09523809523809557 30.001 C 1 30.001 1.9047619047619044 31.001000000000005 1.9047619047619044 32.001000000000005 L 1.9047619047619044 38.001 C 1.9047619047619044 39.001 0.9047619047619044 40.001 -0.09523809523809557 40.001 L -0.09523809523809557 40.001 C -1 40.001 -1.9047619047619044 39.001 -1.9047619047619044 38.001 Z "
                                                                fill="rgba(var(--v-theme-primary))" fill-opacity="1"
                                                                stroke-opacity="1" stroke-linecap="round"
                                                                stroke-width="0" stroke-dasharray="0"
                                                                class="apexcharts-bar-area" index="0"
                                                                clip-path="url(#gridRectMaskqycvbxyj)"
                                                                pathTo="M -1.9047619047619044 38.001 L -1.9047619047619044 32.001000000000005 C -1.9047619047619044 31.001000000000005 -0.9047619047619044 30.001 0.09523809523809557 30.001 L 0.09523809523809557 30.001 C 1 30.001 1.9047619047619044 31.001000000000005 1.9047619047619044 32.001000000000005 L 1.9047619047619044 38.001 C 1.9047619047619044 39.001 0.9047619047619044 40.001 -0.09523809523809557 40.001 L -0.09523809523809557 40.001 C -1 40.001 -1.9047619047619044 39.001 -1.9047619047619044 38.001 Z "
                                                                pathFrom="M -1.9047619047619044 40.001 L -1.9047619047619044 40.001 L 1.9047619047619044 40.001 L 1.9047619047619044 40.001 L 1.9047619047619044 40.001 L 1.9047619047619044 40.001 L 1.9047619047619044 40.001 L -1.9047619047619044 40.001 Z"
                                                                cy="30" cx="1.9047619047619044" j="0" val="5"
                                                                barHeight="10" barWidth="3.809523809523809"></path>
                                                            <path id="SvgjsPath1562"
                                                                d="M 5.714285714285714 38.001 L 5.714285714285714 26.001 C 5.714285714285714 25.001 6.714285714285714 24.001 7.714285714285714 24.001 L 7.714285714285714 24.001 C 8.619047619047619 24.001 9.523809523809524 25.001 9.523809523809524 26.001 L 9.523809523809524 38.001 C 9.523809523809524 39.001 8.523809523809524 40.001 7.523809523809524 40.001 L 7.523809523809524 40.001 C 6.619047619047619 40.001 5.714285714285714 39.001 5.714285714285714 38.001 Z "
                                                                fill="rgba(var(--v-theme-primary))" fill-opacity="1"
                                                                stroke-opacity="1" stroke-linecap="round"
                                                                stroke-width="0" stroke-dasharray="0"
                                                                class="apexcharts-bar-area" index="0"
                                                                clip-path="url(#gridRectMaskqycvbxyj)"
                                                                pathTo="M 5.714285714285714 38.001 L 5.714285714285714 26.001 C 5.714285714285714 25.001 6.714285714285714 24.001 7.714285714285714 24.001 L 7.714285714285714 24.001 C 8.619047619047619 24.001 9.523809523809524 25.001 9.523809523809524 26.001 L 9.523809523809524 38.001 C 9.523809523809524 39.001 8.523809523809524 40.001 7.523809523809524 40.001 L 7.523809523809524 40.001 C 6.619047619047619 40.001 5.714285714285714 39.001 5.714285714285714 38.001 Z "
                                                                pathFrom="M 5.714285714285714 40.001 L 5.714285714285714 40.001 L 9.523809523809524 40.001 L 9.523809523809524 40.001 L 9.523809523809524 40.001 L 9.523809523809524 40.001 L 9.523809523809524 40.001 L 5.714285714285714 40.001 Z"
                                                                cy="24" cx="9.523809523809524" j="1" val="8"
                                                                barHeight="16" barWidth="3.809523809523809"></path>
                                                            <path id="SvgjsPath1564"
                                                                d="M 13.333333333333332 38.001 L 13.333333333333332 28.001 C 13.333333333333332 27.001 14.333333333333332 26.001 15.333333333333332 26.001 L 15.333333333333332 26.001 C 16.238095238095237 26.001 17.142857142857142 27.001 17.142857142857142 28.001 L 17.142857142857142 38.001 C 17.142857142857142 39.001 16.142857142857142 40.001 15.142857142857142 40.001 L 15.142857142857142 40.001 C 14.238095238095237 40.001 13.333333333333332 39.001 13.333333333333332 38.001 Z "
                                                                fill="rgba(var(--v-theme-primary))" fill-opacity="1"
                                                                stroke-opacity="1" stroke-linecap="round"
                                                                stroke-width="0" stroke-dasharray="0"
                                                                class="apexcharts-bar-area" index="0"
                                                                clip-path="url(#gridRectMaskqycvbxyj)"
                                                                pathTo="M 13.333333333333332 38.001 L 13.333333333333332 28.001 C 13.333333333333332 27.001 14.333333333333332 26.001 15.333333333333332 26.001 L 15.333333333333332 26.001 C 16.238095238095237 26.001 17.142857142857142 27.001 17.142857142857142 28.001 L 17.142857142857142 38.001 C 17.142857142857142 39.001 16.142857142857142 40.001 15.142857142857142 40.001 L 15.142857142857142 40.001 C 14.238095238095237 40.001 13.333333333333332 39.001 13.333333333333332 38.001 Z "
                                                                pathFrom="M 13.333333333333332 40.001 L 13.333333333333332 40.001 L 17.142857142857142 40.001 L 17.142857142857142 40.001 L 17.142857142857142 40.001 L 17.142857142857142 40.001 L 17.142857142857142 40.001 L 13.333333333333332 40.001 Z"
                                                                cy="26" cx="17.142857142857142" j="2" val="7"
                                                                barHeight="14" barWidth="3.809523809523809"></path>
                                                            <path id="SvgjsPath1566"
                                                                d="M 20.952380952380953 38.001 L 20.952380952380953 18.001 C 20.952380952380953 17.001 21.952380952380953 16.001 22.952380952380953 16.001 L 22.952380952380953 16.001 C 23.857142857142858 16.001 24.761904761904763 17.001 24.761904761904763 18.001 L 24.761904761904763 38.001 C 24.761904761904763 39.001 23.761904761904763 40.001 22.761904761904763 40.001 L 22.761904761904763 40.001 C 21.857142857142858 40.001 20.952380952380953 39.001 20.952380952380953 38.001 Z "
                                                                fill="rgba(var(--v-theme-primary))" fill-opacity="1"
                                                                stroke-opacity="1" stroke-linecap="round"
                                                                stroke-width="0" stroke-dasharray="0"
                                                                class="apexcharts-bar-area" index="0"
                                                                clip-path="url(#gridRectMaskqycvbxyj)"
                                                                pathTo="M 20.952380952380953 38.001 L 20.952380952380953 18.001 C 20.952380952380953 17.001 21.952380952380953 16.001 22.952380952380953 16.001 L 22.952380952380953 16.001 C 23.857142857142858 16.001 24.761904761904763 17.001 24.761904761904763 18.001 L 24.761904761904763 38.001 C 24.761904761904763 39.001 23.761904761904763 40.001 22.761904761904763 40.001 L 22.761904761904763 40.001 C 21.857142857142858 40.001 20.952380952380953 39.001 20.952380952380953 38.001 Z "
                                                                pathFrom="M 20.952380952380953 40.001 L 20.952380952380953 40.001 L 24.761904761904763 40.001 L 24.761904761904763 40.001 L 24.761904761904763 40.001 L 24.761904761904763 40.001 L 24.761904761904763 40.001 L 20.952380952380953 40.001 Z"
                                                                cy="16" cx="24.761904761904763" j="3" val="12"
                                                                barHeight="24" barWidth="3.809523809523809"></path>
                                                            <path id="SvgjsPath1568"
                                                                d="M 28.57142857142857 38.001 L 28.57142857142857 30.001 C 28.57142857142857 29.001 29.57142857142857 28.001 30.57142857142857 28.001 L 30.57142857142857 28.001 C 31.476190476190474 28.001 32.38095238095238 29.001 32.38095238095238 30.001 L 32.38095238095238 38.001 C 32.38095238095238 39.001 31.38095238095238 40.001 30.38095238095238 40.001 L 30.38095238095238 40.001 C 29.476190476190474 40.001 28.57142857142857 39.001 28.57142857142857 38.001 Z "
                                                                fill="rgba(var(--v-theme-primary))" fill-opacity="1"
                                                                stroke-opacity="1" stroke-linecap="round"
                                                                stroke-width="0" stroke-dasharray="0"
                                                                class="apexcharts-bar-area" index="0"
                                                                clip-path="url(#gridRectMaskqycvbxyj)"
                                                                pathTo="M 28.57142857142857 38.001 L 28.57142857142857 30.001 C 28.57142857142857 29.001 29.57142857142857 28.001 30.57142857142857 28.001 L 30.57142857142857 28.001 C 31.476190476190474 28.001 32.38095238095238 29.001 32.38095238095238 30.001 L 32.38095238095238 38.001 C 32.38095238095238 39.001 31.38095238095238 40.001 30.38095238095238 40.001 L 30.38095238095238 40.001 C 29.476190476190474 40.001 28.57142857142857 39.001 28.57142857142857 38.001 Z "
                                                                pathFrom="M 28.57142857142857 40.001 L 28.57142857142857 40.001 L 32.38095238095238 40.001 L 32.38095238095238 40.001 L 32.38095238095238 40.001 L 32.38095238095238 40.001 L 32.38095238095238 40.001 L 28.57142857142857 40.001 Z"
                                                                cy="28" cx="32.38095238095238" j="4" val="6"
                                                                barHeight="12" barWidth="3.809523809523809"></path>
                                                            <path id="SvgjsPath1570"
                                                                d="M 36.19047619047619 38.001 L 36.19047619047619 28.001 C 36.19047619047619 27.001 37.19047619047619 26.001 38.19047619047619 26.001 L 38.19047619047619 26.001 C 39.095238095238095 26.001 40 27.001 40 28.001 L 40 38.001 C 40 39.001 39 40.001 38 40.001 L 38 40.001 C 37.095238095238095 40.001 36.19047619047619 39.001 36.19047619047619 38.001 Z "
                                                                fill="rgba(var(--v-theme-primary))" fill-opacity="1"
                                                                stroke-opacity="1" stroke-linecap="round"
                                                                stroke-width="0" stroke-dasharray="0"
                                                                class="apexcharts-bar-area" index="0"
                                                                clip-path="url(#gridRectMaskqycvbxyj)"
                                                                pathTo="M 36.19047619047619 38.001 L 36.19047619047619 28.001 C 36.19047619047619 27.001 37.19047619047619 26.001 38.19047619047619 26.001 L 38.19047619047619 26.001 C 39.095238095238095 26.001 40 27.001 40 28.001 L 40 38.001 C 40 39.001 39 40.001 38 40.001 L 38 40.001 C 37.095238095238095 40.001 36.19047619047619 39.001 36.19047619047619 38.001 Z "
                                                                pathFrom="M 36.19047619047619 40.001 L 36.19047619047619 40.001 L 40 40.001 L 40 40.001 L 40 40.001 L 40 40.001 L 40 40.001 L 36.19047619047619 40.001 Z"
                                                                cy="26" cx="40" j="5" val="7" barHeight="14"
                                                                barWidth="3.809523809523809"></path>
                                                            <path id="SvgjsPath1572"
                                                                d="M 43.80952380952381 38.001 L 43.80952380952381 12.001 C 43.80952380952381 11.001 44.80952380952381 10.001 45.80952380952381 10.001 L 45.80952380952381 10.001 C 46.714285714285715 10.001 47.61904761904762 11.001 47.61904761904762 12.001 L 47.61904761904762 38.001 C 47.61904761904762 39.001 46.61904761904762 40.001 45.61904761904762 40.001 L 45.61904761904762 40.001 C 44.714285714285715 40.001 43.80952380952381 39.001 43.80952380952381 38.001 Z "
                                                                fill="rgba(var(--v-theme-primary))" fill-opacity="1"
                                                                stroke-opacity="1" stroke-linecap="round"
                                                                stroke-width="0" stroke-dasharray="0"
                                                                class="apexcharts-bar-area" index="0"
                                                                clip-path="url(#gridRectMaskqycvbxyj)"
                                                                pathTo="M 43.80952380952381 38.001 L 43.80952380952381 12.001 C 43.80952380952381 11.001 44.80952380952381 10.001 45.80952380952381 10.001 L 45.80952380952381 10.001 C 46.714285714285715 10.001 47.61904761904762 11.001 47.61904761904762 12.001 L 47.61904761904762 38.001 C 47.61904761904762 39.001 46.61904761904762 40.001 45.61904761904762 40.001 L 45.61904761904762 40.001 C 44.714285714285715 40.001 43.80952380952381 39.001 43.80952380952381 38.001 Z "
                                                                pathFrom="M 43.80952380952381 40.001 L 43.80952380952381 40.001 L 47.61904761904762 40.001 L 47.61904761904762 40.001 L 47.61904761904762 40.001 L 47.61904761904762 40.001 L 47.61904761904762 40.001 L 43.80952380952381 40.001 Z"
                                                                cy="10" cx="47.61904761904762" j="6" val="15"
                                                                barHeight="30" barWidth="3.809523809523809"></path>
                                                            <path id="SvgjsPath1574"
                                                                d="M 51.42857142857142 38.001 L 51.42857142857142 2.001 C 51.42857142857142 1.001 52.42857142857142 0.001 53.42857142857142 0.001 L 53.42857142857142 0.001 C 54.33333333333333 0.001 55.238095238095234 1.001 55.238095238095234 2.001 L 55.238095238095234 38.001 C 55.238095238095234 39.001 54.238095238095234 40.001 53.238095238095234 40.001 L 53.238095238095234 40.001 C 52.33333333333333 40.001 51.42857142857142 39.001 51.42857142857142 38.001 Z "
                                                                fill="rgba(var(--v-theme-primary))" fill-opacity="1"
                                                                stroke-opacity="1" stroke-linecap="round"
                                                                stroke-width="0" stroke-dasharray="0"
                                                                class="apexcharts-bar-area" index="0"
                                                                clip-path="url(#gridRectMaskqycvbxyj)"
                                                                pathTo="M 51.42857142857142 38.001 L 51.42857142857142 2.001 C 51.42857142857142 1.001 52.42857142857142 0.001 53.42857142857142 0.001 L 53.42857142857142 0.001 C 54.33333333333333 0.001 55.238095238095234 1.001 55.238095238095234 2.001 L 55.238095238095234 38.001 C 55.238095238095234 39.001 54.238095238095234 40.001 53.238095238095234 40.001 L 53.238095238095234 40.001 C 52.33333333333333 40.001 51.42857142857142 39.001 51.42857142857142 38.001 Z "
                                                                pathFrom="M 51.42857142857142 40.001 L 51.42857142857142 40.001 L 55.238095238095234 40.001 L 55.238095238095234 40.001 L 55.238095238095234 40.001 L 55.238095238095234 40.001 L 55.238095238095234 40.001 L 51.42857142857142 40.001 Z"
                                                                cy="0" cx="55.238095238095234" j="7" val="20"
                                                                barHeight="40" barWidth="3.809523809523809"></path>
                                                            <g id="SvgjsG1557" class="apexcharts-bar-goals-markers">
                                                                <g id="SvgjsG1559"
                                                                    className="apexcharts-bar-goals-groups"
                                                                    class="apexcharts-hidden-element-shown"
                                                                    clip-path="url(#gridRectMarkerMaskqycvbxyj)"></g>
                                                                <g id="SvgjsG1561"
                                                                    className="apexcharts-bar-goals-groups"
                                                                    class="apexcharts-hidden-element-shown"
                                                                    clip-path="url(#gridRectMarkerMaskqycvbxyj)"></g>
                                                                <g id="SvgjsG1563"
                                                                    className="apexcharts-bar-goals-groups"
                                                                    class="apexcharts-hidden-element-shown"
                                                                    clip-path="url(#gridRectMarkerMaskqycvbxyj)"></g>
                                                                <g id="SvgjsG1565"
                                                                    className="apexcharts-bar-goals-groups"
                                                                    class="apexcharts-hidden-element-shown"
                                                                    clip-path="url(#gridRectMarkerMaskqycvbxyj)"></g>
                                                                <g id="SvgjsG1567"
                                                                    className="apexcharts-bar-goals-groups"
                                                                    class="apexcharts-hidden-element-shown"
                                                                    clip-path="url(#gridRectMarkerMaskqycvbxyj)"></g>
                                                                <g id="SvgjsG1569"
                                                                    className="apexcharts-bar-goals-groups"
                                                                    class="apexcharts-hidden-element-shown"
                                                                    clip-path="url(#gridRectMarkerMaskqycvbxyj)"></g>
                                                                <g id="SvgjsG1571"
                                                                    className="apexcharts-bar-goals-groups"
                                                                    class="apexcharts-hidden-element-shown"
                                                                    clip-path="url(#gridRectMarkerMaskqycvbxyj)"></g>
                                                                <g id="SvgjsG1573"
                                                                    className="apexcharts-bar-goals-groups"
                                                                    class="apexcharts-hidden-element-shown"
                                                                    clip-path="url(#gridRectMarkerMaskqycvbxyj)"></g>
                                                            </g>
                                                            <g id="SvgjsG1558"
                                                                class="apexcharts-bar-shadows apexcharts-hidden-element-shown">
                                                            </g>
                                                        </g>
                                                        <g id="SvgjsG1556"
                                                            class="apexcharts-datalabels apexcharts-hidden-element-shown"
                                                            data:realIndex="0"></g>
                                                    </g>
                                                    <line id="SvgjsLine1586" x1="-8.333333333333334" y1="0"
                                                        x2="61.666666666666664" y2="0" stroke="#b6b6b6"
                                                        stroke-dasharray="0" stroke-width="1" stroke-linecap="butt"
                                                        class="apexcharts-ycrosshairs"></line>
                                                    <line id="SvgjsLine1587" x1="-8.333333333333334" y1="0"
                                                        x2="61.666666666666664" y2="0" stroke-dasharray="0"
                                                        stroke-width="0" stroke-linecap="butt"
                                                        class="apexcharts-ycrosshairs-hidden"></line>
                                                    <g id="SvgjsG1588" class="apexcharts-xaxis"
                                                        transform="translate(0, 0)">
                                                        <g id="SvgjsG1589" class="apexcharts-xaxis-texts-g"
                                                            transform="translate(0, -4)"></g>
                                                    </g>
                                                    <g id="SvgjsG1597" class="apexcharts-yaxis-annotations"></g>
                                                    <g id="SvgjsG1598" class="apexcharts-xaxis-annotations"></g>
                                                    <g id="SvgjsG1599" class="apexcharts-point-annotations"></g>
                                                </g>
                                            </svg>
                                            <div class="apexcharts-tooltip apexcharts-theme-dark"
                                                style="left: -20.2589px; top: 11.5px;">
                                                <div class="apexcharts-tooltip-series-group apexcharts-active"
                                                    style="order: 1; display: flex;"><span
                                                        class="apexcharts-tooltip-marker"
                                                        style="background-color: rgba(var(--v-theme-primary));"></span>
                                                    <div class="apexcharts-tooltip-text"
                                                        style="font-family: inherit; font-size: 12px;">
                                                        <div class="apexcharts-tooltip-y-group"><span
                                                                class="apexcharts-tooltip-text-y-label"></span></div>
                                                        <div class="apexcharts-tooltip-goals-group"><span
                                                                class="apexcharts-tooltip-text-goals-label"></span><span
                                                                class="apexcharts-tooltip-text-goals-value"></span>
                                                        </div>
                                                        <div class="apexcharts-tooltip-z-group"><span
                                                                class="apexcharts-tooltip-text-z-label"></span><span
                                                                class="apexcharts-tooltip-text-z-value"></span></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div
                                                class="apexcharts-yaxistooltip apexcharts-yaxistooltip-0 apexcharts-yaxistooltip-left apexcharts-theme-dark">
                                                <div class="apexcharts-yaxistooltip-text"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
                                    <h2 class="text-h4">{{ totalUnit.toLocaleString('th-TH') }}</h2>
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
                                    <h2 class="text-h4">{{ totalValue.toLocaleString('th-TH') }}</h2>
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
                                    <h2 class="text-h4"> {{ totalArea.toLocaleString('th-TH') }}</h2>
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
                                        {{ formatNumber(parseFloat(averagePricePerSqm), true) }}
                                    </h2>
                                    <p class="textSecondary mt-1 text-15">ราคาเฉลี่ย/ตร.ม.</p>
                                </div>
                            </div>
                        </div> <span class="v-card__underlay"></span>
                    </div>
                </div>
            </div>

        </v-col>
        <v-col cols="12" sm="12" lg="8">
            <SalesOverview />
        </v-col>
        <v-col cols="12" sm="12" lg="4">
            <TotalSales />
        </v-col>

        <v-col cols="12" sm="12" lg="12">
            <VCard elevation="10">

                <v-card-text>
                    <TheActivityTimeline />
                </v-card-text>
            </VCard>
        </v-col>
    </v-row>
</template>

