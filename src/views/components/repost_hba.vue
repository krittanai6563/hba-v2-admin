<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useDate } from 'vuetify/lib/framework.mjs';

import ExcelJS from 'exceljs';
import type {Cell, Worksheet, Column, Style, Font, Alignment, Fill, BorderStyle} from 'exceljs';

import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import html2canvas from 'html2canvas';

const date = useDate();



const today = new Date();
const currentGregorianYear = today.getFullYear(); // 1. ดึงปี ค.ศ. (เช่น 2025)
const currentBuddhistYearNum = currentGregorianYear + 543; // 2. บวก 543 (เช่น 2568)
const currentBuddhistYearStr = currentBuddhistYearNum.toString(); // 3. แปลงเป็น "2568"
const previousBuddhistYearStr = (currentBuddhistYearNum - 1).toString(); // 4. "2567"

const tab = ref('monthly');
const isPdfLoading = ref(false);

interface Metrics {
    total_value: number;
    total_area: number;
    total_units: number;
    average_price_per_sqm: number;
}

interface MemberSubmission {
    member_id: string;
    name: string;
    role: 'user' | 'admin' | 'master';
    member_type: string;
    total_submitted_count: number;
    submissions_by_year: Record<string, number>;
    submissions_in_period: Record<string, number[]>;
}

interface SummaryData {
    yearly_data: Record<string, Record<string, Metrics>>;
    monthly_data: Record<string, Record<number, Record<string, Metrics>>>;
    quarterly_data?: Record<string, Record<number, Record<string, Metrics>>>;
    region_data?: Record<string, Record<number, Record<string, Record<string, Metrics>>>>;
    membership_data?: MemberSubmission[];
}


// (ไฟล์ repost_hba.vue บรรทัดที่ 40)
const availableMonths = computed(() => {
    const { currentBuddhistYear, currentMonthIndex } = getCurrentPeriod();
    const selectedYears = selectyear.value;

    // ตรวจสอบว่า "ปีปัจจุบัน" อยู่ในเงื่อนไขหรือไม่ (ยังไม่เลือกปี หรือ เลือกปีปัจจุบัน)
    const isCurrentYearInContext =
        selectedYears.length === 0 ||
        selectedYears.includes(currentBuddhistYear);

    if (isCurrentYearInContext) {
        // ⬇️ นี่คือส่วนที่ซ่อนเดือนที่ยังไม่มาถึง
        // โดยการตัด (slice) อาร์เรย์ Months ให้เหลือแค่ถึงเดือนปัจจุบัน
        return Months.slice(0, currentMonthIndex);
    } else {
        // ถ้าเลือกเฉพาะปีในอดีต: ให้แสดง 12 เดือนเต็ม
        return Months;
    }
});


// src/views/components/repost_hba.vue (ประมาณบรรทัดที่ 67)

const availableQuarters = computed(() => {
    // ใช้ฟังก์ชันที่แก้ไขแล้วเพื่อดึงปีและเดือนปัจจุบัน
    const { currentBuddhistYear, currentMonthIndex } = getCurrentPeriod(); // currentMonthIndex = 11 (พ.ย.)
    const selectedYears = selectyear.value;

    // ตรวจสอบว่ากำลังดูบริบทของ "ปีปัจจุบัน" อยู่หรือไม่
    const isCurrentYearInContext =
        selectedYears.length === 0 ||
        selectedYears.includes(currentBuddhistYear);

    if (isCurrentYearInContext) {
        // ถ้าเป็นปีปัจจุบัน หรือยังไม่ได้เลือกปี: กรองไตรมาสที่ผ่านมา/ถึงปัจจุบัน
        return Quarters.filter(q => {
            // ⬇️ เปลี่ยนการตรวจสอบจากเดือนสุดท้าย เป็น เดือนแรกของไตรมาส
            // ตรวจสอบเดือนแรกของไตรมาสนั้น (เช่น ไตรมาส 1 คือเดือน 1)
            const firstMonthOfQuarter = q.months[0];
            // อนุญาตให้เลือกไตรมาสที่เดือนแรก <= เดือนปัจจุบัน
            return firstMonthOfQuarter <= currentMonthIndex; // 10 <= 11 เป็น จริง (Q4 แสดงผล)
        });
    } else {
        // ถ้าเลือกปีในอดีต: ให้แสดง 4 ไตรมาสเต็ม
        return Quarters;
    }
});


const selectyear = ref<string[]>([]);
const selectMonths = ref<string[]>([]);
const selectQuarters = ref<string[]>([]);
const year = [currentBuddhistYearStr, previousBuddhistYearStr];
const Months = [
    'มกราคม',
    'กุมภาพันธ์',
    'มีนาคม',
    'เมษายน',
    'พฤษภาคม',
    'มิถุนายน',
    'กรกฎาคม',
    'สิงหาคม',
    'กันยายน',
    'ตุลาคม',
    'พฤศจิกายน',
    'ธันวาคม'
];
const MonthAbbreviations = [
    'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
    'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
];
const Quarters = [
    { name: 'ไตรมาส 1', index: 1, months: [1, 2, 3], names: ['มกราคม', 'กุมภาพันธ์', 'มีนาคม'] },
    { name: 'ไตรมาส 2', index: 2, months: [4, 5, 6], names: ['เมษายน', 'พฤษภาคม', 'มิถุนายน'] },
    { name: 'ไตรมาส 3', index: 3, months: [7, 8, 9], names: ['กรกฎาคม', 'สิงหาคม', 'กันยายน'] },
    { name: 'ไตรมาส 4', index: 4, months: [10, 11, 12], names: ['ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'] },
];
const quarterMap = Quarters.reduce((acc, q) => { acc[q.name] = q.index; return acc; }, {} as Record<string, number>);

const regionCategories = [
    'กรุงเทพปริมณฑล',
    'ภาคกลาง',
    'ภาคเหนือ',
    'ภาคตะวันออกเฉียงเหนือ',
    'ภาคใต้',
    'ภาคตะวันออก',
    'ภาคตะวันตก',
    'รวมทั่วประเทศ'
];

const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user';
const summaryData = ref<SummaryData | null>(null);
const chartSeries = ref<any[]>([]);
const categoryOrder = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป', 'รวม'];
const valueCategories = categoryOrder.filter(cat => cat !== 'รวม');
const metricRows = [
    {
        key: 'total_units',
        name: 'จำนวนหลัง',
        format: (v: number) => v.toLocaleString('th-TH', { maximumFractionDigits: 0 })
    },
    {
        key: 'total_value',
        name: 'มูลค่ารวม (บาท)',
        format: (v: number) => v.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
    {
        key: 'total_area',
        name: 'พื้นที่ใช้สอย (ตร.ม.)',
        format: (v: number) => v.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
    {
        key: 'average_price_per_sqm',
        name: 'ราคาเฉลี่ย/ตร.ม.',
        format: (v: number) => v.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
];


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

const quartersToMonthsNames = computed<string[]>(() => {
    if (selectQuarters.value.length === 0) return [];

    let monthIndices: number[] = [];
    selectQuarters.value.forEach(qName => {
        const quarter = Quarters.find(q => q.name === qName);
        if (quarter) monthIndices.push(...quarter.months);
    });
    const uniqueMonthIndices = Array.from(new Set(monthIndices)).sort((a, b) => a - b);
    return uniqueMonthIndices.map(index => Months[index - 1]);
});

const getCurrentPeriod = () => {
    const today = new Date();
    // ⬇️ แก้ไข 2 บรรทัดนี้ ⬇️
    const currentGregorianYear = today.getFullYear();
    const currentBuddhistYear = (currentGregorianYear + 543).toString();
    // ⬆️ สิ้นสุดส่วนที่แก้ไข ⬆️
    const currentMonthIndex = today.getMonth() + 1;
    return { currentBuddhistYear, currentMonthIndex };
};
onMounted(() => {
    const { currentBuddhistYear } = getCurrentPeriod();
    if (!selectyear.value || selectyear.value.length === 0) {
        if (year.includes(currentBuddhistYear)) {
            selectyear.value = [currentBuddhistYear];
        }
    }

    fetchSummary(selectyear.value, selectMonths.value, selectQuarters.value);
});

const fetchSummary = async (
    currentYears: string[],
    currentMonths: string[],
    currentQuarters: string[]
) => {

    if (!currentYears || currentYears.length === 0) {
        console.error('Please select at least one year.');
        summaryData.value = null;
        chartSeries.value = [];
        return;
    }
    const data: any = {
        user_id: userId,
        buddhist_year: currentYears,
        role: userRole
    };



    let indices: number[] = [];
    if (currentQuarters.length > 0) {
        currentQuarters.forEach(qName => {
            const quarter = Quarters.find(q => q.name === qName);
            if (quarter) indices.push(...quarter.months);
        });
    }
    const manualMonthIndices = currentMonths.map(m => monthMap[m]).filter(Boolean) as number[];
    if (manualMonthIndices.length > 0) {
        indices.push(...manualMonthIndices);
    }

    const monthsToFetch = Array.from(new Set(indices)).sort((a, b) => a - b);
    const quartersToFetch = currentQuarters.map((quarterName: string) => quarterMap[quarterName] || null).filter(Boolean);



    if (monthsToFetch.length > 0) {
        data.months = monthsToFetch;
    }
    if (quartersToFetch.length > 0) {
        data.quarters = quartersToFetch;
    }

    console.log('Sending data to backend:', data);

    try {

        const res = await fetch('https://uat.hba-sales.org/backend/repost_admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        if (!res.ok) {

            const errorText = await res.text();
            console.error(`❌ HTTP Error: ${res.status} ${res.statusText}`, errorText);
            throw new Error(`Server responded with ${res.status}: ${errorText}`);
        }

        const responseData: SummaryData = await res.json();

        summaryData.value = responseData;
        updateChartData(responseData);

    } catch (err) {
        console.error('❌ Error fetching summary:', err);
        summaryData.value = null;
        chartSeries.value = [];
    }
};

const combinedTargetMonthIndices = computed<number[]>(() => {
    let indices: number[] = [];

    const { currentBuddhistYear, currentMonthIndex } = getCurrentPeriod();
    const selectedYears = selectyear.value;

    const isCurrentYearSelected =
        selectedYears.length === 1 && selectedYears.includes(currentBuddhistYear);

    const selectedQuarters = selectQuarters.value;
    if (selectedQuarters.length > 0) {
        selectedQuarters.forEach(qName => {
            const quarter = Quarters.find(q => q.name === qName);
            if (quarter) {
                let monthsToInclude = quarter.months;

                // ⬇️⬇️⬇️ [ส่วนที่แก้ไข] กรองเดือนสำหรับปีปัจจุบัน ⬇️⬇️⬇️
                if (isCurrentYearSelected) {
                    // ถ้าเลือกปีปัจจุบัน: กรองเอาเฉพาะเดือนที่มีค่า <= เดือนปัจจุบัน
                    monthsToInclude = monthsToInclude.filter(monthIndex =>
                        monthIndex <= currentMonthIndex
                    );
                }
                // ⬆️⬆️⬆️ [ส่วนที่แก้ไข] ⬆️⬆️⬆️

                indices.push(...monthsToInclude);
            }
        });
    }

    // 2. ดึงเดือนจาก "เดือน" ที่เลือก (ส่วนนี้ยังทำงานได้เหมือนเดิม เพราะ availableMonths กรองใน UI แล้ว)
    const manualMonthIndices = selectMonths.value.map(m => monthMap[m]).filter(Boolean) as number[];
    if (manualMonthIndices.length > 0) {
        indices.push(...manualMonthIndices);
    }

    // 3. คืนค่าเป็นรายการเดือนที่ไม่ซ้ำกัน และเรียงลำดับแล้ว
    return Array.from(new Set(indices)).sort((a, b) => a - b);
});
const updateChartData = (data: SummaryData) => {
    const finalSeries: any[] = [];
    const dataForAverageCalc: number[][] = [];
    let finalCategories: string[] = categoryOrder;


    const sortedYears = [...selectyear.value].sort((a, b) => a.localeCompare(b, 'th-TH'));
    const selectedYears = sortedYears;

    const selectedMonths = selectMonths.value;
    const selectedQuarters = selectQuarters.value;
    const getValue = (dataObj: Metrics | undefined) => (dataObj?.total_value || 0);



    /*
    const getSelectedMonthIndices = () => {
        ... (ตรรกะ if/else ที่ผิด) ...
    };
    */

    const targetMonths = combinedTargetMonthIndices.value;



    if (selectedYears.length === 1 && (selectedMonths.length > 1 || selectedQuarters.length > 0 || targetMonths.length > 1)) {

        finalCategories = categoryOrder;
        const selectedYear = selectedYears[0];
        const monthsToDisplay = targetMonths;

        monthsToDisplay.forEach((monthIndex) => {
            const monthlyData = categoryOrder.map((category) => {
                const dataObj = data.monthly_data[selectedYear]?.[monthIndex]?.[category];
                return getValue(dataObj);
            });

            dataForAverageCalc.push(monthlyData);
            finalSeries.push({
                name: `${Months[monthIndex - 1]} ${selectedYear}`,
                type: 'column',
                data: monthlyData
            });
        });
    } else if (selectedYears.length > 1 && (selectedMonths.length > 0 || selectedQuarters.length > 0 || targetMonths.length > 0)) {

        finalCategories = categoryOrder;
        const monthsToDisplay = targetMonths;

        monthsToDisplay.forEach((monthIndex) => {
            const monthName = Months[monthIndex - 1];


            selectedYears.forEach((year) => {
                const monthlyData = categoryOrder.map((category) => getValue(data.monthly_data[year]?.[monthIndex]?.[category]));

                dataForAverageCalc.push(monthlyData);
                finalSeries.push({ name: `${monthName} ${year}`, type: 'column', data: monthlyData });
            });
        });
    } else if (selectedYears.length === 1 && selectedMonths.length === 0 && selectedQuarters.length === 0) {

        finalCategories = categoryOrder;
        const yearlyData = categoryOrder.map((category) => getValue(data.yearly_data[selectedYears[0]]?.[category]));
        dataForAverageCalc.push(yearlyData);
        finalSeries.push({ name: `ปี ${selectedYears[0]}`, type: 'column', data: dataForAverageCalc[0] });
    } else if (selectedMonths.length === 0 && selectedQuarters.length === 0 && selectedYears.length > 1) {

        finalCategories = categoryOrder;

        selectedYears.forEach((year) => {
            const yearlyData = categoryOrder.map((category) => getValue(data.yearly_data[year]?.[category]));
            dataForAverageCalc.push(yearlyData);
            finalSeries.push({ name: `ปี ${year}`, type: 'column', data: yearlyData });
        });
    } else if (selectedYears.length === 1 && selectedMonths.length === 1 && selectedQuarters.length === 0 && targetMonths.length === 1) {

        finalCategories = categoryOrder;
        const selectedYear = selectedYears[0];
        const monthIndex = targetMonths[0];

        const monthlyData = categoryOrder.map((category) => getValue(data.monthly_data[selectedYear]?.[monthIndex]?.[category]));
        dataForAverageCalc.push(monthlyData);
        finalSeries.push({ name: `${Months[monthIndex - 1]} ${selectedYear}`, type: 'column', data: monthlyData });
    }


    let finalChartSeries = finalSeries;
    const averageData: number[] = [];
    const numSeries = dataForAverageCalc.length;
    const numCategories = categoryOrder.length;

    if (numSeries > 0) {
        for (let i = 0; i < numCategories; i++) {
            let sum = 0;
            for (let j = 0; j < numSeries; j++) {
                sum += (dataForAverageCalc[j][i] || 0);
            }
            averageData.push(Math.round(sum / numSeries));
        }

        finalChartSeries.push({
            name: 'ค่าเฉลี่ย',
            type: 'line',
            data: averageData,
        });
    }

    chartSeries.value = finalChartSeries;
};


const chartSubtitle = computed(() => {

    const sortedYears = [...selectyear.value].sort((a, b) => a.localeCompare(b, 'th-TH'));
    const firstYear = sortedYears[0];
    const lastYear = sortedYears[sortedYears.length - 1];

    const selectedMonths = selectMonths.value;
    const selectedQuarters = selectQuarters.value;

    const yearText = sortedYears.length === 1 ? `ปี ${firstYear}` :
        sortedYears.length > 1 ? `ปี ${firstYear} - ปี ${lastYear}` : '';



    /*
    let monthIndices: number[] = [];
    if (selectedQuarters.length > 0) {
        ...
    } else if (selectedMonths.length > 0) {
        ...
    }
    */
    let monthIndices: number[] = combinedTargetMonthIndices.value;


    const sortedMonthIndices = Array.from(new Set(monthIndices)).sort((a, b) => a - b);
    const firstMonthIndex = sortedMonthIndices[0];
    const lastMonthIndex = sortedMonthIndices[sortedMonthIndices.length - 1];
    const firstMonthName = firstMonthIndex ? Months[firstMonthIndex - 1] : '';
    const lastMonthName = lastMonthIndex ? Months[lastMonthIndex - 1] : '';


    if (selectedQuarters.length > 0) {
        const quarterNames = selectedQuarters.join(', ');

        if (firstMonthName && lastMonthName) {

            return `ไตรมาส: ${quarterNames} (${firstMonthName} - ${lastMonthName}) ${yearText}`;
        }
        return `ไตรมาส: ${quarterNames} ${yearText}`;
    }


    if (sortedMonthIndices.length > 1) {


        if (sortedYears.length === 1) {
            const monthIndicesString = sortedMonthIndices.join(',');
            const Q = Quarters.find(q => q.months.join(',') === monthIndicesString);

            if (Q) {

                return `${yearText} - ${Q.name} (${Q.names.join(' - ')}`;
            }
        }


        if (sortedYears.length === 1) {
            return `เดือน ${firstMonthName} - ${lastMonthName} ปี ${firstYear}`;
        } else if (sortedYears.length > 1) {

            return `เดือน ${firstMonthName} - ${lastMonthName} ปี ${firstYear} - ปี ${lastYear}`;
        }
    }

    else if (sortedMonthIndices.length === 1) {
        const selectedMonthName = firstMonthName;
        if (sortedYears.length === 1) {
            return `เดือน ${selectedMonthName} ปี ${firstYear}`;
        } else if (sortedYears.length > 1) {

            return `เดือน ${selectedMonthName} ปี ${firstYear} - ปี ${lastYear}`;
        }
    }

    else if (sortedYears.length > 0) {
        if (sortedYears.length === 1) {
            return `สรุปรายปี ${firstYear}`;
        } else {
            return `เปรียบเทียบรายปี ${firstYear} - ปี ${lastYear}`;
        }
    }


    const { currentBuddhistYear } = getCurrentPeriod();
    const currentMonthName = Months[new Date().getMonth()];


    if (sortedYears.length === 1 && sortedYears[0] === currentBuddhistYear) {
        return `สรุปยอดเซ็นสัญญา ถึงเดือน ${currentMonthName} ปี ${currentBuddhistYear}`;
    }

    return 'กรุณาเลือกข้อมูลเพื่อแสดงผล';
});




/*
watch(selectQuarters, (newQuarters) => {
    if (newQuarters.length > 0) {
        selectMonths.value = quartersToMonthsNames.value;
    }
}, { immediate: false });
*/


watch(
    [selectyear, selectMonths, selectQuarters],

    ([newYears, newMonths, newQuarters]) => {

        fetchSummary(newYears, newMonths, newQuarters);
    },
    { immediate: false, deep: true }
);

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
        },
        zoom: {
            enabled: true,
            type: 'xy'
        }
    },
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '50%',

        },
        line: {

            curve: 'smooth'
        }
    },
    dataLabels: {
        enabled: true,
        position: 'top',
        offsetY: -15,
        style: {
            fontSize: '10px'
        },
        formatter: (value: number | null | undefined) => {
            if (value === null || value === undefined) {
                return '';
            }
            return value >= 1000 ? value.toLocaleString('th-TH') : value.toString();
        }
    },
    stroke: {
        width: 2,
        curve: 'smooth'
    },
    grid: {
        show: true,
        strokeDashArray: 4,
        borderColor: 'rgba(0, 0, 0, 0.1)'
    },

    yaxis: {
        labels: {
            show: false
        }
    },
    xaxis: {
        categories: categoryOrder,
        labels: {
            style: {
                fontSize: '12px',
                colors: '#6c757d'
            }
        },
        tickPlacement: 'on'
    },

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



interface TableCellData {
    [key: string]: number;
}
interface TableRow {
    metricKey: keyof Metrics | 'average_price_per_sqm';
    metricName: string;
    format: (v: number) => string;
    data: TableCellData;
    growth: {
        mom: number | null;
        ytd: number | null;
    }
}
interface TableCategory {
    categoryName: string;
    rows: TableRow[];
}
interface RegionCategoryGroup {
    regionName: string;
    categories: TableCategory[];
}
interface GrowthRateMetrics {
    MoM: number | null;
    YoY: number | null;
    QoQ: number | null;
    YTD: number | null;
}
interface GrowthRatePeriods {
    [key: string]: GrowthRateMetrics;
}
interface GrowthRateCategory {
    categoryName: string;
    total_value: GrowthRatePeriods;
    total_units: GrowthRatePeriods;
    total_value_raw: Record<string, number>;
    total_units_raw: Record<string, number>;
}
type MetricGrowthKey = 'total_value' | 'total_units';

// *** [เพิ่ม]: Type Definition สำหรับข้อมูล Period (แก้ไขปัญหา Type) ***
type PeriodItem = {
    key: string;
    label: string;
    year: string;
    monthIndex?: number | undefined;
};
// ************************************************


const showQoQ = computed(() => {


    const targetMonths = combinedTargetMonthIndices.value;
    if (targetMonths.length === 0) return false;


    const hasQuarterEndMonth = targetMonths.some(monthIndex =>
        Quarters.some(q => q.months[q.months.length - 1] === monthIndex)
    );

    return hasQuarterEndMonth;

});

const getRegionalMetrics = (period: PeriodItem, region: string, category: string): Metrics => {
    let metrics: Metrics | undefined;


    if (region === 'รวมทั่วประเทศ') {
        if (period.monthIndex && period.monthIndex !== 0) {

            metrics = summaryData.value?.monthly_data[period.year]?.[period.monthIndex]?.[category];
        } else if (!period.monthIndex && period.year !== 'TOTAL') {

            metrics = summaryData.value?.yearly_data[period.year]?.[category];
        }
    }


    if (!metrics && region !== 'รวมทั่วประเทศ') {

        const regionDataForYear = summaryData.value?.region_data?.[period.year];

        if (period.monthIndex && period.monthIndex !== 0) {

            metrics = regionDataForYear?.[period.monthIndex]?.[region]?.[category];
        }


        else if (!period.monthIndex && period.year !== 'TOTAL' && regionDataForYear) {


            let annualMetrics: Metrics = {
                total_value: 0,
                total_area: 0,
                total_units: 0,
                average_price_per_sqm: 0
            };
            let foundData = false;


            const monthKeys = Object.keys(regionDataForYear);

            for (const monthKey of monthKeys) {
                const monthIndexAsNumber = parseInt(monthKey);

                const monthlyMetrics = regionDataForYear[monthIndexAsNumber]?.[region]?.[category];

                if (monthlyMetrics) {
                    annualMetrics.total_value += monthlyMetrics.total_value;
                    annualMetrics.total_area += monthlyMetrics.total_area;
                    annualMetrics.total_units += monthlyMetrics.total_units;
                    foundData = true;
                }
            }

            if (foundData) {

                annualMetrics.average_price_per_sqm = (annualMetrics.total_area > 0) ? (annualMetrics.total_value / annualMetrics.total_area) : 0;
                metrics = annualMetrics;
            }
        }
    }


    if (!metrics) {
        return { total_value: 0, total_area: 0, total_units: 0, average_price_per_sqm: 0 };
    }
    return metrics;
};


const tablePeriods = computed<PeriodItem[]>(() => {
    const selectedYears = selectyear.value;
    const { currentBuddhistYear, currentMonthIndex } = getCurrentPeriod();
    let periods: PeriodItem[] = [];
    const sortedYears = [...selectedYears].sort((a, b) => a.localeCompare(b, 'th-TH'));

    const currentTargetMonthIndices = combinedTargetMonthIndices.value;

    if (currentTargetMonthIndices.length > 0) {


        const yearsToProcess = sortedYears.length > 0 ? sortedYears : [currentBuddhistYear];

        yearsToProcess.forEach(year => {
            currentTargetMonthIndices.forEach(monthIndex => {
                periods.push({
                    key: `M${monthIndex}Y${year}`,
                    label: `${MonthAbbreviations[monthIndex - 1]} ${year.substring(2, 4)}`,
                    year: year,
                    monthIndex: monthIndex
                });
            });
        });
    } else {


        if (sortedYears.length > 0) {

            sortedYears.forEach(year => {



                const isCurrent = (year === currentBuddhistYear);

                const loopEnd = isCurrent ? currentMonthIndex : 12;

                for (let i = 1; i <= loopEnd; i++) {
                    periods.push({
                        key: `M${i}Y${year}`,
                        label: `${MonthAbbreviations[i - 1]} ${year.substring(2, 4)}`,
                        year: year,
                        monthIndex: i
                    });
                }

            });
        } else {


            if (year.includes(currentBuddhistYear)) {
                for (let i = 1; i <= currentMonthIndex; i++) {
                    periods.push({
                        key: `M${i}Y${currentBuddhistYear}`,
                        label: `${MonthAbbreviations[i - 1]} ${currentBuddhistYear.substring(2, 4)}`,
                        year: currentBuddhistYear,
                        monthIndex: i
                    });
                }
            }
        }
    }


    periods.sort((a, b) => {
        if (a.year !== b.year) {
            return a.year.localeCompare(b.year, 'th-TH');
        }
        const monthA = a.monthIndex || 0;
        const monthB = b.monthIndex || 0;
        return monthA - monthB;
    });

    let finalPeriods = periods;

    if (finalPeriods.length > 1) {
        finalPeriods.push({
            key: 'TOTAL_PERIODS',
            label: 'รวมทุกช่วง',
            year: 'TOTAL',
            monthIndex: 0
        });
    }

    return finalPeriods;
});



const monthlyReportTableData = computed<TableCategory[]>(() => {
    if (!summaryData.value) {
        return [];
    }

    const currentPeriods = tablePeriods.value;
    const grandTotalPeriodKey = 'TOTAL_PERIODS';
    const allCategories = [...valueCategories, 'รวม'];


    const lp = lastPeriod.value;

    const finalTable: TableCategory[] = [];

    allCategories.forEach(categoryName => {
        const categoryData: TableCategory = {
            categoryName: categoryName,
            rows: []
        };

        metricRows.forEach(metric => {
            const row: TableRow = {
                metricKey: metric.key as keyof Metrics | 'average_price_per_sqm',
                metricName: metric.name,
                format: metric.format,
                data: {},

                growth: { mom: null, ytd: null }
            };

            let totalMetricValue = 0;
            let totalValueForAvg = 0;
            let totalAreaForAvg = 0;


            currentPeriods.forEach(p => {
                if (p.key === grandTotalPeriodKey) return;


                const getMetrics = (period: PeriodItem, category: string): Metrics => {
                    let metrics: Metrics | undefined;
                    if (period.monthIndex && period.monthIndex !== 0) {
                        metrics = summaryData.value?.monthly_data[period.year]?.[period.monthIndex]?.[category];
                    } else if (!period.monthIndex && period.year !== 'TOTAL') {
                        metrics = summaryData.value?.yearly_data[period.year]?.[category];
                    }
                    if (!metrics) return { total_value: 0, total_area: 0, total_units: 0, average_price_per_sqm: 0 };
                    return metrics;
                };

                const metrics = getMetrics(p, categoryName);
                const metricValue: number = metrics[metric.key as keyof Metrics] || 0;
                row.data[p.key] = metricValue;


                if (metric.key !== 'average_price_per_sqm') {
                    totalMetricValue += metricValue;
                }
                totalValueForAvg += metrics.total_value;
                totalAreaForAvg += metrics.total_area;
            });


            if (currentPeriods.some(p => p.key === grandTotalPeriodKey)) {
                let grandTotalMetricValue: number;
                if (metric.key === 'average_price_per_sqm') {
                    grandTotalMetricValue = totalAreaForAvg > 0 ? (totalValueForAvg / totalAreaForAvg) : 0;
                } else {
                    grandTotalMetricValue = totalMetricValue;
                }
                row.data[grandTotalPeriodKey] = grandTotalMetricValue;
            }


            if (lp && lp.monthIndex) {
                const currentYear = lp.year;
                const currentMonth = lp.monthIndex;
                const prevYear = (parseInt(currentYear) - 1).toString();


                const getMetricsForPeriod = (year: string, month: number, category: string): Metrics => {
                    const metrics = summaryData.value?.monthly_data[year]?.[month]?.[category];
                    return metrics || { total_value: 0, total_area: 0, total_units: 0, average_price_per_sqm: 0 };
                };


                const getAggregatedValueForMetric = (year: string, startMonth: number, endMonth: number, category: string, metricKey: keyof Metrics): number => {
                    let sum = 0;
                    const monthlyData = summaryData.value?.monthly_data[year];
                    if (!monthlyData) return 0;
                    for (let month = startMonth; month <= endMonth; month++) {
                        const metrics = monthlyData[month]?.[category];
                        if (metrics) {
                            sum += metrics[metricKey] || 0;
                        }
                    }
                    return sum;
                };




                const currentMetrics = getMetricsForPeriod(currentYear, currentMonth, categoryName);
                const currentValue = currentMetrics[metric.key as keyof Metrics] || 0;

                const prevMonth = (currentMonth === 1) ? 12 : currentMonth - 1;
                const prevMonthYear = (currentMonth === 1) ? prevYear : currentYear;
                const prevMonthMetrics = getMetricsForPeriod(prevMonthYear, prevMonth, categoryName);
                const prevMonthValue = prevMonthMetrics[metric.key as keyof Metrics] || 0;


                if (prevMonthValue !== 0) {
                    row.growth.mom = ((currentValue / prevMonthValue) - 1) * 100;
                } else if (currentValue > 0) {
                    row.growth.mom = 100;
                }

                if (prevYTDValue !== 0) {
                    row.growth.ytd = ((currentYTDValue / prevYTDValue) - 1) * 100;
                } else if (currentYTDValue > 0) {
                    row.growth.ytd = 100;
                }
            }


            categoryData.rows.push(row);
        });
        finalTable.push(categoryData);
    });

    return finalTable;
});


const getRegionalAggregatedValue = (year: string, startMonth: number, endMonth: number, region: string, category: string, metricKey: keyof Metrics): number => {
    let sum = 0;
    const regionDataForYear = summaryData.value?.region_data?.[year];

    if (metricKey === 'average_price_per_sqm') return 0; // ต้องคำนวณจาก Value/Area รวม

    if (!regionDataForYear) return 0;

    for (let month = startMonth; month <= endMonth; month++) {
        if (month < 1 || month > 12) continue;

        const monthlyMetrics = regionDataForYear[month]?.[region]?.[category];
        if (monthlyMetrics) {
            sum += monthlyMetrics[metricKey] || 0;
        }
    }
    return sum;
};

// src/views/components/repost_hba.vue (ประมาณบรรทัดที่ 687)

const regionReportTableData = computed<TableCategory[]>(() => {
    if (!summaryData.value) {
        return [];
    }

    const currentPeriods = tablePeriods.value;
    const grandTotalPeriodKey = 'TOTAL_PERIODS';

    const finalTable: TableCategory[] = [];


    regionCategories.forEach(regionName => {
        const categoryData: TableCategory = {
            categoryName: regionName,
            rows: []
        };

        metricRows.forEach(metric => {
            const row: TableRow = {
                metricKey: metric.key as keyof Metrics | 'average_price_per_sqm',
                metricName: metric.name,
                format: metric.format,
                data: {},
                growth: { mom: null, ytd: null }
            };

            let totalMetricValue = 0;
            let totalValueForAvg = 0;
            let totalAreaForAvg = 0;


            currentPeriods.filter(p => p.key !== grandTotalPeriodKey).forEach(p => {
                const periodKey = p.key;



                const metrics = getRegionalMetrics(p, regionName, 'รวม');

                let metricValue: number = metrics[metric.key as keyof Metrics] || 0;

                row.data[periodKey] = metricValue;


                if (metric.key !== 'average_price_per_sqm') {
                    totalMetricValue += metricValue;
                }
                totalValueForAvg += metrics.total_value;
                totalAreaForAvg += metrics.total_area;
            });


            if (currentPeriods.some(p => p.key === grandTotalPeriodKey)) {
                let grandTotalMetricValue: number;

                if (metric.key === 'average_price_per_sqm') {
                    grandTotalMetricValue = totalAreaForAvg > 0 ? (totalValueForAvg / totalAreaForAvg) : 0;
                } else {
                    grandTotalMetricValue = totalMetricValue;
                }

                row.data[grandTotalPeriodKey] = grandTotalMetricValue;
            }

            // ⬇️ [ส่วนที่เพิ่ม: การคำนวณอัตราเติบโต MoM และ YTD] ⬇️
            const lp = lastPeriod.value;
            if (lp && lp.monthIndex) {
                const currentYear = lp.year;
                const currentMonth = lp.monthIndex;
                const prevYear = (parseInt(currentYear) - 1).toString();
                const categoryName = 'รวม';

                // --- 1. MoM Calculation ---
                const currentMetrics = getRegionalMetrics(lp, regionName, categoryName);
                const currentValue = currentMetrics[metric.key as keyof Metrics] || 0;

                const prevMonth = (currentMonth === 1) ? 12 : currentMonth - 1;
                const prevMonthYear = (currentMonth === 1) ? prevYear : currentYear;

                const prevPeriod: PeriodItem = {
                    key: `M${prevMonth}Y${prevMonthYear}`,
                    label: `MoM`,
                    year: prevMonthYear,
                    monthIndex: prevMonth
                };
                const prevMonthMetrics = getRegionalMetrics(prevPeriod, regionName, categoryName);
                const prevMonthValue = prevMonthMetrics[metric.key as keyof Metrics] || 0;

                if (prevMonthValue !== 0) {
                    row.growth.mom = ((currentValue / prevMonthValue) - 1) * 100;
                } else if (currentValue > 0) {
                    row.growth.mom = 100;
                } else {
                    row.growth.mom = 0;
                }

                // --- 2. YTD Calculation ---
                let currentYTDValue: number;
                let prevYTDValue: number;

                if (metric.key === 'average_price_per_sqm') {
                    const currentYTD_Value = getRegionalAggregatedValue(currentYear, 1, currentMonth, regionName, categoryName, 'total_value');
                    const currentYTD_Area = getRegionalAggregatedValue(currentYear, 1, currentMonth, regionName, categoryName, 'total_area');
                    currentYTDValue = (currentYTD_Area > 0) ? (currentYTD_Value / currentYTD_Area) : 0;

                    const prevYTD_Value = getRegionalAggregatedValue(prevYear, 1, currentMonth, regionName, categoryName, 'total_value');
                    const prevYTD_Area = getRegionalAggregatedValue(prevYear, 1, currentMonth, regionName, categoryName, 'total_area');
                    prevYTDValue = (prevYTD_Area > 0) ? (prevYTD_Value / prevYTD_Area) : 0;
                } else {
                    currentYTDValue = getRegionalAggregatedValue(currentYear, 1, currentMonth, regionName, categoryName, metric.key as keyof Metrics);
                    prevYTDValue = getRegionalAggregatedValue(prevYear, 1, currentMonth, regionName, categoryName, metric.key as keyof Metrics);
                }

                if (prevYTDValue !== 0) {
                    row.growth.ytd = ((currentYTDValue / prevYTDValue) - 1) * 100;
                } else if (currentYTDValue > 0) {
                    row.growth.ytd = 100;
                } else {
                    row.growth.ytd = 0;
                }
            }
            // ⬆️ [สิ้นสุดส่วนที่เพิ่ม] ⬆️

            categoryData.rows.push(row);
        });
        finalTable.push(categoryData);
    });

    return finalTable;
});



// src/views/components/repost_hba.vue (ประมาณบรรทัดที่ 802)

const regionAndCategoryReportTableData = computed<RegionCategoryGroup[]>(() => {
    if (!summaryData.value) {
        return [];
    }

    const currentPeriods = tablePeriods.value;
    const grandTotalPeriodKey = 'TOTAL_PERIODS';

    const allPriceCategories = [...valueCategories, 'รวม'];

    const finalTable: RegionCategoryGroup[] = [];


    regionCategories.forEach(regionName => {
        const regionGroup: RegionCategoryGroup = {
            regionName: regionName,
            categories: []
        };


        allPriceCategories.forEach(categoryName => {
            const categoryData: TableCategory = {
                categoryName: categoryName,
                rows: []
            };


            metricRows.forEach(metric => {
                const row: TableRow = {
                    metricKey: metric.key as keyof Metrics | 'average_price_per_sqm',
                    metricName: metric.name,
                    format: metric.format,
                    data: {},
                    growth: { mom: null, ytd: null }
                };

                let totalMetricValue = 0;
                let totalValueForAvg = 0;
                let totalAreaForAvg = 0;


                currentPeriods.filter(p => p.key !== grandTotalPeriodKey).forEach(p => {
                    const periodKey = p.key;



                    const metrics = getRegionalMetrics(p, regionName, categoryName);

                    let metricValue: number = metrics[metric.key as keyof Metrics] || 0;

                    row.data[periodKey] = metricValue;


                    if (metric.key !== 'average_price_per_sqm') {
                        totalMetricValue += metricValue;
                    }
                    totalValueForAvg += metrics.total_value;
                    totalAreaForAvg += metrics.total_area;
                });


                if (currentPeriods.some(p => p.key === grandTotalPeriodKey)) {
                    let grandTotalMetricValue: number;

                    if (metric.key === 'average_price_per_sqm') {
                        grandTotalMetricValue = totalAreaForAvg > 0 ? (totalValueForAvg / totalAreaForAvg) : 0;
                    } else {
                        grandTotalMetricValue = totalMetricValue;
                    }

                    row.data[grandTotalPeriodKey] = grandTotalMetricValue;
                }

                // ⬇️ [ส่วนที่เพิ่ม: การคำนวณอัตราเติบโต MoM และ YTD] ⬇️
                const lp = lastPeriod.value;
                if (lp && lp.monthIndex) {
                    const currentYear = lp.year;
                    const currentMonth = lp.monthIndex;
                    const prevYear = (parseInt(currentYear) - 1).toString();
                    // categoryName ถูกกำหนดในลูปภายนอกแล้ว

                    // --- 1. MoM Calculation ---
                    const currentMetrics = getRegionalMetrics(lp, regionName, categoryName);
                    const currentValue = currentMetrics[metric.key as keyof Metrics] || 0;

                    const prevMonth = (currentMonth === 1) ? 12 : currentMonth - 1;
                    const prevMonthYear = (currentMonth === 1) ? prevYear : currentYear;

                    const prevPeriod: PeriodItem = {
                        key: `M${prevMonth}Y${prevMonthYear}`,
                        label: `MoM`,
                        year: prevMonthYear,
                        monthIndex: prevMonth
                    };
                    const prevMonthMetrics = getRegionalMetrics(prevPeriod, regionName, categoryName);
                    const prevMonthValue = prevMonthMetrics[metric.key as keyof Metrics] || 0;

                    if (prevMonthValue !== 0) {
                        row.growth.mom = ((currentValue / prevMonthValue) - 1) * 100;
                    } else if (currentValue > 0) {
                        row.growth.mom = 100;
                    } else {
                        row.growth.mom = 0;
                    }

                    // --- 2. YTD Calculation ---
                    let currentYTDValue: number;
                    let prevYTDValue: number;

                    if (metric.key === 'average_price_per_sqm') {
                        const currentYTD_Value = getRegionalAggregatedValue(currentYear, 1, currentMonth, regionName, categoryName, 'total_value');
                        const currentYTD_Area = getRegionalAggregatedValue(currentYear, 1, currentMonth, regionName, categoryName, 'total_area');
                        currentYTDValue = (currentYTD_Area > 0) ? (currentYTD_Value / currentYTD_Area) : 0;

                        const prevYTD_Value = getRegionalAggregatedValue(prevYear, 1, currentMonth, regionName, categoryName, 'total_value');
                        const prevYTD_Area = getRegionalAggregatedValue(prevYear, 1, currentMonth, regionName, categoryName, 'total_area');
                        prevYTDValue = (prevYTD_Area > 0) ? (prevYTD_Value / prevYTD_Area) : 0;
                    } else {
                        currentYTDValue = getRegionalAggregatedValue(currentYear, 1, currentMonth, regionName, categoryName, metric.key as keyof Metrics);
                        prevYTDValue = getRegionalAggregatedValue(prevYear, 1, currentMonth, regionName, categoryName, metric.key as keyof Metrics);
                    }

                    if (prevYTDValue !== 0) {
                        row.growth.ytd = ((currentYTDValue / prevYTDValue) - 1) * 100;
                    } else if (currentYTDValue > 0) {
                        row.growth.ytd = 100;
                    } else {
                        row.growth.ytd = 0;
                    }
                }
                // ⬆️ [สิ้นสุดส่วนที่เพิ่ม] ⬆️

                categoryData.rows.push(row);
            });
            regionGroup.categories.push(categoryData);
        });
        finalTable.push(regionGroup);
    });

    return finalTable;
});


const polarChartPriceData = computed(() => {
    const data = monthlyReportTableData.value;
    if (!data || data.length === 0) {
        return { series: [], labels: [] };
    }

    const priceLabels = valueCategories;
    const seriesData: number[] = [];

    priceLabels.forEach(categoryName => {

        const categoryGroup = data.find(c => c.categoryName === categoryName);
        if (categoryGroup) {

            const totalValueRow = categoryGroup.rows.find(r => r.metricKey === 'total_value');



            const periodKey = totalValueRow?.data['TOTAL_PERIODS'] !== undefined
                ? 'TOTAL_PERIODS'
                : tablePeriods.value[0]?.key;

            const totalValue = (periodKey ? totalValueRow?.data[periodKey] : 0) || 0;


            seriesData.push(totalValue);
        } else {
            seriesData.push(0);
        }
    });

    return {
        series: seriesData,
        labels: priceLabels,
        totalValueSum: seriesData.reduce((sum, value) => sum + value, 0)
    };
});


const polarChartRegionData = computed(() => {
    const data = regionReportTableData.value;
    if (!data || data.length === 0) {
        return { series: [], labels: [] };
    }


    const regionLabels = regionCategories.filter(reg => reg !== 'รวมทั่วประเทศ');
    const seriesData: number[] = [];

    regionLabels.forEach(regionName => {
        const regionGroup = data.find(c => c.categoryName === regionName);
        if (regionGroup) {
            const totalValueRow = regionGroup.rows.find(r => r.metricKey === 'total_value');


            const periodKey = totalValueRow?.data['TOTAL_PERIODS'] !== undefined
                ? 'TOTAL_PERIODS'
                : tablePeriods.value[0]?.key;

            const totalValue = (periodKey ? totalValueRow?.data[periodKey] : 0) || 0;


            seriesData.push(totalValue); // ใช้ค่ามูลค่าดิบ
        } else {
            seriesData.push(0);
        }
    });

    return {
        series: seriesData,
        labels: regionLabels,
        totalValueSum: seriesData.reduce((sum, value) => sum + value, 0)
    };
});


const polarChartOptions = computed(() => {

    // **[เพิ่ม]: กำหนดชุดสีที่แตกต่างกัน**
    const chartColors = [
        '#3F51B5', // Indigo
        '#03A9F4', // Light Blue
        '#4CAF50', // Green
        '#FFC107', // Amber
        '#F44336', // Red
        '#9C27B0', // Purple
        '#00BCD4', // Cyan
        '#FF9800', // Orange
        '#795548', // Brown
        '#607D8B'  // Blue Grey
    ];

    return {
        chart: {
            type: 'polarArea',
            height: 400,
            fontFamily: 'inherit',
            foreColor: '#adb0bb',
        },

        // **[เพิ่ม]: กำหนดสีให้กับกราฟ**
        colors: chartColors,

        stroke: {

        },
        fill: {
            opacity: 0.9
        },
        legend: {
            position: 'bottom',
            offsetY: 0
        },


        yaxis: {
            labels: {
                show: false
            }
        },


        dataLabels: {
            enabled: true,

            formatter: function (val: number, opts: any) {

                // 1. ดึงค่ามูลค่าดิบ (Raw Value) ของชิ้นส่วนกราฟปัจจุบัน
                const rawValue = opts.w.globals.series[opts.seriesIndex];


                // 2. คำนวณ % (val คือค่าเปอร์เซ็นต์)
                const percentage = (Number(val) || 0).toFixed(2);


                // ⬇️ เปลี่ยนการจัดรูปแบบให้แสดงมูลค่าดิบ
                const formattedValue = rawValue.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });


                // แสดงผลลัพธ์: [มูลค่าจริง, (เปอร์เซ็นต์)]
                return [
                    formattedValue + ' บาท', // เพิ่ม " บาท" ตรงนี้เพื่อให้ชัดเจน
                    `(${percentage}%)`
                ];
            },
            // ...
            style: {
                fontSize: '9px',
            },
            dropShadow: {
                enabled: true,
                top: 1,
                left: 1,
                blur: 1,
                opacity: 0.5
            }
        },

        tooltip: {
            y: {
                formatter: (val: number) => {
                    // ใช้ค่าดิบ (Raw Value) โดยตรง
                    const actualValue = val;

                    // ⬇️ เปลี่ยนการจัดรูปแบบให้แสดงทศนิยม 2 ตำแหน่ง
                    return actualValue.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' บาท';
                }
            }
        },


        plotOptions: {
            polarArea: {
                rings: {
                    strokeWidth: 1,
                    strokeColor: '#e0e0e0',
                    strokeDashArray: 4
                },
                spokes: {
                    strokeWidth: 1,
                    strokeColor: '#e0e0e0',
                    strokeDashArray: 4
                },
            }
        }
    }

});

const generateMockMemberData = (): MemberSubmission[] => {
    const memberData = summaryData.value?.membership_data || [];


    if (Array.isArray(memberData)) {
        return memberData as MemberSubmission[];
    }

    console.warn('Membership data format is incorrect or empty:', memberData);
    return [];
};


const memberSubmissionSummary = computed(() => {
    const allMembers = generateMockMemberData();
    const users = allMembers.filter(u => u.role === 'user');
    const allUsersCount = users.length;

    const targetPeriods = tablePeriods.value.filter(p => p.key !== 'TOTAL_PERIODS');
    const hasFilters = targetPeriods.length > 0 &&
        (selectyear.value.length > 0 || selectMonths.value.length > 0 || selectQuarters.value.length > 0);











    let submittedInPeriod_Set = new Set<string>();













    const periodCounts: { key: string, label: string, count: number }[] = [];











    if (targetPeriods.length > 0) {
        targetPeriods.forEach(period => {
            let countForThisPeriod = 0;




            users.forEach(user => {
                let hasSubmittedThisPeriod = false;











                if (period.monthIndex) {



                    if (user.submissions_in_period[period.year]?.includes(period.monthIndex)) {
                        hasSubmittedThisPeriod = true;
                    }
                } else if (period.year) {



                    if ((user.submissions_by_year[period.year] || 0) > 0) {
                        hasSubmittedThisPeriod = true;
                    }
                }

                if (hasSubmittedThisPeriod) {
                    submittedInPeriod_Set.add(user.member_id);




                    countForThisPeriod++;




                }
            });












            periodCounts.push({
                key: period.key,
                label: `สมาชิกที่กรอก ${period.label}`,
                count: countForThisPeriod
            });
        });
    } else {












        users.forEach(user => {
            if (user.total_submitted_count > 0) {
                submittedInPeriod_Set.add(user.member_id);
            }
        });
    }
    const submittedInPeriod_Count = submittedInPeriod_Set.size;
    const notSubmittedInPeriod_Count = allUsersCount - submittedInPeriod_Count;

    const submittedLabel = hasFilters ? 'สมาชิกที่กรอก (ในช่วงที่เลือก)' : 'สมาชิกที่เคยกรอก (ทั้งหมด)';
    const notSubmittedLabel = hasFilters ? 'สมาชิกที่ไม่ได้กรอก (ในช่วงที่เลือก)' : 'สมาชิกที่ไม่เคยกรอก (ทั้งหมด)';

    return {
        totalUsers: allUsersCount,
        totalUsersIncludingAdmin: allMembers.length,
        submittedTotal: submittedInPeriod_Count,
        notSubmittedTotal: notSubmittedInPeriod_Count,
        submittedLabel: submittedLabel,
        notSubmittedLabel: notSubmittedLabel,
        donutData: [submittedInPeriod_Count, notSubmittedInPeriod_Count],
        donutLabels: [submittedLabel, notSubmittedLabel],
        periodCounts: periodCounts,
    };

});


const memberListChartData = computed(() => {

    const allMembers = generateMockMemberData();
    const users = allMembers.filter(u => u.role === 'user');
    const targetPeriods = tablePeriods.value.filter(p => p.key !== 'TOTAL_PERIODS');
    const yearsToAggregateSet = new Set<string>();
    if (targetPeriods.length > 0) {
        targetPeriods.forEach(p => yearsToAggregateSet.add(p.year));
    }

    const yearsToAggregate = Array.from(yearsToAggregateSet);

    const aggregatedUsers = users.map(user => {
        let totalSubmissionsInPeriod = 0;
        let yearsToSum: string[];
        if (yearsToAggregate.length > 0) {
            yearsToSum = yearsToAggregate;
        } else {
            yearsToSum = Object.keys(user.submissions_by_year);
        }
        yearsToSum.forEach(year => {
            totalSubmissionsInPeriod += (user.submissions_by_year[year] || 0);
        });

        return {
            name: user.name,
            submissions: totalSubmissionsInPeriod,
        };
    }).filter(u => u.submissions > 0);
    aggregatedUsers.sort((a, b) => b.submissions - a.submissions);

    return {
        series: [{
            name: `จำนวนสัญญาที่กรอก (รวมยอดทั้งปี)`,
            data: aggregatedUsers.map(u => u.submissions)
        }],
        categories: aggregatedUsers.map(u => u.name)
    };
});


const barChartHeight = computed(() => {
    const len = memberListChartData.value.categories.length;
    return len > 0
        ? 350 + (len * 30)
        : 350;
});


const barChartOptions = ref({
    chart: {
        type: 'bar',
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
        toolbar: { show: false },
    },
    plotOptions: {
        bar: {
            horizontal: true,
            dataLabels: {
                position: 'top',
            },
            borderRadius: 4
        }
    },
    dataLabels: {
        enabled: true,
        formatter: (val: number) => val.toLocaleString('th-TH', { maximumFractionDigits: 0 }),
        offsetX: 0
    },
    xaxis: {
        categories: [] as string[],
        title: {
            text: 'จำนวนสัญญา'
        }
    },
    tooltip: {
        y: {
            formatter: (val: number) => val.toLocaleString('th-TH', { maximumFractionDigits: 0 })
        }
    },
    grid: {
        show: true,
        strokeDashArray: 4,
        borderColor: 'rgba(0, 0, 0, 0.1)'
    },
    legend: { show: false }
});


watch(memberListChartData, (newData) => {

    barChartOptions.value = {
        ...barChartOptions.value,
        xaxis: {
            ...barChartOptions.value.xaxis,
            categories: newData.categories
        }
    };


    barChartKey.value += 1;
});
const barChartKey = ref(0);

const donutChartOptions = computed(() => ({
    chart: {
        type: 'donut',
        height: 350,
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
    },
    labels: memberSubmissionSummary.value.donutLabels,
    legend: {
        position: 'bottom',
        offsetY: 0
    },
    dataLabels: {
        enabled: true,
        formatter: (val: number, opts: any) => {
            const sum = memberSubmissionSummary.value.donutData.reduce((a, b) => a + b, 0);
            const absoluteValue = opts.w.globals.series[opts.seriesIndex];
            const percentage = (absoluteValue / sum) * 100;
            return `${absoluteValue} (${percentage.toFixed(1)}%)`;
        }
    },
    tooltip: {
        y: {
            formatter: (val: number, opts: any) => {
                const sum = memberSubmissionSummary.value.donutData.reduce((a, b) => a + b, 0);
                const percentage = (val / sum) * 100;
                return `${val.toLocaleString('th-TH', { maximumFractionDigits: 0 })} (${percentage.toFixed(1)}%)`;
            }
        }
    },
    responsive: [{
        breakpoint: 480,
        options: {
            chart: { width: 200 },
            legend: { position: 'bottom' }
        }
    }]
}));


interface MemberMonthlyData {
    name: string;
    submissions: Record<string, string>;
    role: 'user' | 'admin' | 'master';
    member_type: string;
    total_submitted_in_period: number;
}

const memberMonthlySubmissionTableData = computed(() => {
    const members = summaryData.value?.membership_data || [];
    const periodsToDisplay = tablePeriods.value.filter(p => p.key !== 'TOTAL_PERIODS');

    if (members.length === 0) {
        return [];
    }
    const filteredMembers = members.filter(member =>
        ['สามัญ', 'สมทบ', 'วิสามัญ ก'].includes(member.member_type)
    );

    return filteredMembers.map(member => {
        const submissions: Record<string, 'X' | '-'> = {};
        let calculatedTotalInPeriod = 0;
        periodsToDisplay.forEach(period => {

            const year = period.year;
            const month = period.monthIndex;

            const hasSubmission = (month && member.submissions_in_period[year]?.includes(month)) || false;

            if (hasSubmission) {
                submissions[period.key] = 'X';


                calculatedTotalInPeriod++;
            } else {
                submissions[period.key] = '-';
            }
        });


        return {
            name: member.name,
            member_type: member.member_type,
            total_submitted_in_period: calculatedTotalInPeriod,
            submissions: submissions
        };
    });
});


const getAggregatedValue = (year: string, startMonth: number, endMonth: number, category: string, metricKey: keyof Metrics): number => {
    let sum = 0;
    const monthlyData = summaryData.value?.monthly_data[year];

    if (metricKey === 'average_price_per_sqm') return 0;

    if (!monthlyData) return 0;

    for (let month = startMonth; month <= endMonth; month++) {
        if (month < 1 || month > 12) continue;

        const metrics = monthlyData[month]?.[category];
        if (metrics) {
            sum += metrics[metricKey] || 0;
        }
    }
    return sum;
};

const getMetricValue = (period: PeriodItem, category: string, metricKey: keyof Metrics): number => {
    let metrics: Metrics | undefined;

    if (period.monthIndex && period.monthIndex !== 0) {
        metrics = summaryData.value?.monthly_data[period.year]?.[period.monthIndex]?.[category];
    } else if (!period.monthIndex && period.year !== 'TOTAL') {
        metrics = summaryData.value?.yearly_data[period.year]?.[category];
    }

    return metrics ? (metrics[metricKey as keyof Metrics] || 0) : 0;
};


const growthRateReportTableData = computed<GrowthRateCategory[]>(() => {
    if (!summaryData.value || tablePeriods.value.length === 0) {
        return [];
    }


    const periodsToCalculate = tablePeriods.value.filter(p => p.key !== 'TOTAL_PERIODS');
    const allCategories = [...valueCategories, 'รวม'];
    const finalTable: GrowthRateCategory[] = [];
    const metricsToTrack: MetricGrowthKey[] = ['total_value', 'total_units'];

    allCategories.forEach(categoryName => {


        const categoryData: GrowthRateCategory = {
            categoryName: categoryName,
            total_value: {},
            total_units: {},
            total_value_raw: {},
            total_units_raw: {}
        };


        metricsToTrack.forEach(metricKey => {
            periodsToCalculate.forEach((currentPeriod, index) => {
                const currentValue = getMetricValue(currentPeriod, categoryName, metricKey as keyof Metrics);
                const periodKey = currentPeriod.key;



                categoryData[`${metricKey}_raw`][periodKey] = currentValue;


                let MoM: number | null = null;
                let YoY: number | null = null;
                let QoQ: number | null = null;
                let YTD: number | null = null;


                if (!currentPeriod.monthIndex && currentPeriod.year !== 'TOTAL') {
                    const prevYear = (parseInt(currentPeriod.year) - 1).toString();
                    const prevPeriod: PeriodItem = { key: `Y${prevYear}`, label: `สรุปปี ${prevYear}`, year: prevYear };
                    const prevValue = getMetricValue(prevPeriod, categoryName, metricKey as keyof Metrics);

                    if (prevValue !== 0) {
                        YoY = ((currentValue / prevValue) - 1) * 100;
                    } else if (currentValue > 0) {
                        YoY = 100;
                    }
                }

                if (currentPeriod.monthIndex) {
                    const currentYear = currentPeriod.year;
                    const currentMonth = currentPeriod.monthIndex;


                    if (index > 0) {
                        const prevPeriod = periodsToCalculate[index - 1];
                        const isPreviousMonth = (prevPeriod.monthIndex === currentMonth - 1) && (prevPeriod.year === currentYear);
                        const isJanFromDec = (currentMonth === 1) && (prevPeriod.monthIndex === 12) && (parseInt(prevPeriod.year) === parseInt(currentYear) - 1);

                        if (isPreviousMonth || isJanFromDec) {
                            const prevMonthValue = getMetricValue(prevPeriod, categoryName, metricKey as keyof Metrics);
                            if (prevMonthValue !== 0) {
                                MoM = ((currentValue / prevMonthValue) - 1) * 100;
                            }
                        }
                    }


                    const prevYear = (parseInt(currentYear) - 1).toString();
                    const prevYearPeriod: PeriodItem = {
                        key: `M${currentMonth}Y${prevYear}`, label: `${Months[currentMonth - 1]} ${prevYear}`,
                        year: prevYear, monthIndex: currentMonth
                    };
                    const prevYearValue = getMetricValue(prevYearPeriod, categoryName, metricKey as keyof Metrics);

                    if (prevYearValue !== 0) {
                        YoY = ((currentValue / prevYearValue) - 1) * 100;
                    } else if (currentValue > 0) {
                        YoY = 100;
                    }


                    const currentYTDValue = getAggregatedValue(currentYear, 1, currentMonth, categoryName, metricKey as keyof Metrics);
                    const prevYTDValue = getAggregatedValue(prevYear, 1, currentMonth, categoryName, metricKey as keyof Metrics);

                    if (prevYTDValue !== 0) {
                        YTD = ((currentYTDValue / prevYTDValue) - 1) * 100;
                    } else if (currentYTDValue > 0) {
                        YTD = 100;
                    }


                    const currentQuarter = Quarters.find(q => q.months.includes(currentMonth));

                    if (currentQuarter && currentQuarter.months[currentQuarter.months.length - 1] === currentMonth) {
                        const currentQuarterIndex = currentQuarter.index;

                        let prevQYear = currentYear;
                        let prevQIndex: number;

                        if (currentQuarterIndex > 1) {
                            prevQIndex = currentQuarterIndex - 1;
                        } else {
                            prevQIndex = 4;
                            prevQYear = prevYear;
                        }

                        const prevQuarter = Quarters.find(q => q.index === prevQIndex);

                        if (prevQuarter) {

                            const currentQValue = getAggregatedValue(currentYear, currentQuarter.months[0], currentQuarter.months[currentQuarter.months.length - 1], categoryName, metricKey as keyof Metrics);


                            const prevQValue = getAggregatedValue(prevQYear, prevQuarter.months[0], prevQuarter.months[prevQuarter.months.length - 1], categoryName, metricKey as keyof Metrics);

                            if (prevQValue !== 0) {
                                QoQ = ((currentQValue / prevQValue) - 1) * 100;
                            } else if (currentQValue > 0) {
                                QoQ = 100;
                            }
                        }
                    }
                }


                categoryData[metricKey][periodKey] = {
                    MoM,
                    YoY,
                    QoQ,
                    YTD
                };
            });
        });
        finalTable.push(categoryData);
    });

    return finalTable;
});

const lastPeriod = computed(() => {
    const periods = tablePeriods.value.filter(p => p.key !== 'TOTAL_PERIODS');
    return periods.length > 0 ? periods[periods.length - 1] : null;
});




const monthlySubmissionBarChartData = computed(() => {

    const totalUsers = memberSubmissionSummary.value.totalUsers;


    const periodCounts = memberSubmissionSummary.value.periodCounts;

    if (periodCounts.length === 0 || totalUsers === 0) {

        return { series: [], categories: [] };
    }



    const categories = periodCounts.map(p => p.label.replace('สมาชิกที่กรอก ', ''));


    const seriesDataSubmitted = periodCounts.map(p => p.count);


    const seriesDataNotSubmitted = periodCounts.map(p => totalUsers - p.count);

    return {
        series: [
            { name: 'สมาชิกที่กรอก', data: seriesDataSubmitted },
            { name: 'สมาชิกที่ยังไม่กรอก', data: seriesDataNotSubmitted }
        ],
        categories: categories
    };
});






const monthlyBarChartKey = ref(0);


const monthlyBarChartOptions = ref({
    chart: {
        type: 'bar',
        height: 350,
        stacked: true,
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
        toolbar: { show: true, tools: { download: true } },
    },
    plotOptions: {
        bar: {
            horizontal: false,
            columnWidth: '60%',
        },
    },
    dataLabels: {
        enabled: true,
        formatter: (val: number) => val.toLocaleString('th-TH', { maximumFractionDigits: 0 })
    },
    stroke: {
        show: true,
        width: 2,
        colors: ['transparent']
    },
    xaxis: {
        categories: [] as string[],
        title: {
            text: 'ช่วงเวลาที่เลือก'
        }
    },
    yaxis: {
        title: {
            text: 'จำนวนสมาชิก (คน)'
        }
    },
    fill: {
        opacity: 1
    },
    tooltip: {
        y: {
            formatter: (val: number) => val.toLocaleString('th-TH', { maximumFractionDigits: 0 }) + ' คน'
        }
    },
    legend: {
        position: 'top',
        horizontalAlign: 'center'
    },
    grid: {
        show: true,
        strokeDashArray: 4,
        borderColor: 'rgba(0, 0, 0, 0.1)'
    },
});

watch(monthlySubmissionBarChartData, (newData) => {

    monthlyBarChartOptions.value.xaxis.categories = newData.categories;

    monthlyBarChartKey.value += 1;
});





const memberTypeSubmissionChartData = computed(() => {

    const typesToTrack = ['สามัญ', 'สมทบ', 'วิสามัญ ก'];


    const allMembers = summaryData.value?.membership_data || [];


    const targetPeriods = tablePeriods.value.filter(p => p.key !== 'TOTAL_PERIODS');


    if (targetPeriods.length === 0 || allMembers.length === 0) {
        return { series: [], categories: typesToTrack };
    }

    const submittedSeriesData: number[] = [];
    const notSubmittedSeriesData: number[] = [];


    typesToTrack.forEach(type => {


        const membersInType = allMembers.filter(m => m.member_type === type);
        const totalInType = membersInType.length;


        const submittedMembersInThisType = new Set<string>();

        membersInType.forEach(member => {

            const hasSubmissionInPeriod = targetPeriods.some(period => {
                const year = period.year;
                const month = period.monthIndex;

                return (month && member.submissions_in_period[year]?.includes(month)) || false;
            });

            if (hasSubmissionInPeriod) {
                submittedMembersInThisType.add(member.member_id);
            }
        });

        const submittedCount = submittedMembersInThisType.size;


        submittedSeriesData.push(submittedCount);
        notSubmittedSeriesData.push(totalInType - submittedCount);
    });


    return {
        series: [
            { name: 'สมาชิกที่กรอก (ในช่วงที่เลือก)', data: submittedSeriesData },
            { name: 'สมาชิกที่ยังไม่กรอก (ในช่วงที่เลือก)', data: notSubmittedSeriesData }
        ],
        categories: typesToTrack
    };
});







const memberTypeBarChartKey = ref(0);


const memberTypeBarChartOptions = ref({
    chart: {
        type: 'bar',
        height: 350,
        stacked: true,
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
        toolbar: { show: true, tools: { download: true } },
    },
    plotOptions: {
        bar: {
            horizontal: false,
            columnWidth: '60%',
        },
    },
    dataLabels: {
        enabled: true,
        formatter: (val: number) => val.toLocaleString('th-TH', { maximumFractionDigits: 0 })
    },
    stroke: {
        show: true,
        width: 2,
        colors: ['transparent']
    },
    xaxis: {

        categories: ['สามัญ', 'สมทบ', 'วิสามัญ ก'] as string[],
        title: {
            text: 'ประเภทสมาชิก'
        }
    },
    yaxis: {
        title: {
            text: 'จำนวนสมาชิก (คน)'
        }
    },
    fill: {
        opacity: 1
    },
    tooltip: {
        y: {
            formatter: (val: number) => val.toLocaleString('th-TH', { maximumFractionDigits: 0 }) + ' คน'
        }
    },
    legend: {
        position: 'top',
        horizontalAlign: 'center'
    },
    grid: {
        show: true,
        strokeDashArray: 4,
        borderColor: 'rgba(0, 0, 0, 0.1)'
    },
});


watch(memberTypeSubmissionChartData, (newData) => {

    memberTypeBarChartOptions.value.xaxis.categories = newData.categories;

    memberTypeBarChartKey.value += 1;
});




// ========================================================================
// START: EXCEL EXPORT (ส่วนที่ปรับปรุงใหม่เพื่อความสวยงาม)
// ========================================================================

// 1. STYLE DEFINITIONS (ปรับปรุงใหม่ + แก้ไขบั๊กเส้นขอบ)
const COMMON_FONT_NAME = 'Angsana New';
const TITLE_ROWS = 4; // (Title + Subtitle + Timestamp + Blank)

// --- Typography ---
const FONT_DEFAULT: Partial<Font> = { name: COMMON_FONT_NAME, size: 14, color: { argb: 'FF000000' }, bold: false };
const FONT_HEADER: Partial<Font> = { name: COMMON_FONT_NAME, size: 16, bold: true, color: { argb: 'FF000000' } };
const FONT_TOTAL: Partial<Font> = { name: COMMON_FONT_NAME, size: 15, bold: true, color: { argb: 'FF000000' } };
const FONT_TOTAL_PRIMARY: Partial<Font> = { name: COMMON_FONT_NAME, size: 15, bold: true, color: { argb: 'FF3F51B5' } };

const STYLE_BORDER: Partial<ExcelJS.Borders> = {
    top: { style: 'thin' as BorderStyle }, bottom: { style: 'thin' as BorderStyle },
    left: { style: 'thin' as BorderStyle }, right: { style: 'thin' as BorderStyle }
};

// --- Alignment (เหมือนเดิม) ---
const STYLE_ALIGNMENT_CENTER: Partial<Alignment> = { vertical: 'middle', horizontal: 'center' };
const STYLE_ALIGNMENT_RIGHT: Partial<Alignment> = { vertical: 'middle', horizontal: 'right' };
const STYLE_ALIGNMENT_LEFT: Partial<Alignment> = { vertical: 'middle', horizontal: 'left' };

// --- (แก้ไข) Color Palette - ระบุ Border อย่างชัดเจน ---
const STYLE_HEADER: Partial<Style> = {
    font: FONT_HEADER,
    fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD9E1F2' } } as Fill,
    border: STYLE_BORDER, // <--- ระบุชัดเจน
    alignment: STYLE_ALIGNMENT_CENTER,
};

const STYLE_DEFAULT: Partial<Style> = {
    font: FONT_DEFAULT,
    fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFFFFF' } } as Fill,
    border: STYLE_BORDER, // <--- ระบุชัดเจน
    alignment: STYLE_ALIGNMENT_RIGHT,
    numFmt: 'General'
};

const STYLE_GROUP_LEFT: Partial<Style> = {
    font: { ...FONT_DEFAULT, bold: true },
    fill: STYLE_DEFAULT.fill,
    border: STYLE_BORDER, // <--- (แก้ไข) ระบุชัดเจน
    alignment: STYLE_ALIGNMENT_LEFT,
    numFmt: 'General'
};

const STYLE_BANDED_ROW: Partial<Style> = {
    font: FONT_DEFAULT,
    fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFAFAFA' } } as Fill,
    border: STYLE_BORDER, // <--- (แก้ไข) ระบุชัดเจน
    alignment: STYLE_ALIGNMENT_RIGHT,
    numFmt: 'General'
};

const STYLE_TOTAL: Partial<Style> = {
    font: FONT_TOTAL,
    fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF2F2F2' } } as Fill,
    border: STYLE_BORDER, // <--- (แก้ไข) ระบุชัดเจน
    alignment: STYLE_ALIGNMENT_RIGHT,
    numFmt: 'General'
};

const STYLE_PRIMARY_TOTAL: Partial<Style> = {
    font: FONT_TOTAL_PRIMARY,
    fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD9E1F2' } } as Fill,
    border: STYLE_BORDER, // <--- (แก้ไข) ระบุชัดเจน
    alignment: STYLE_ALIGNMENT_RIGHT,
    numFmt: 'General'
};

// --- (แก้ไข) Growth Styles - ระบุ Border และ Fill ---
const STYLE_GROWTH_GREEN: Partial<Style> = {
    font: { ...FONT_DEFAULT, color: { argb: 'FF00B050' }, bold: true },
    fill: STYLE_DEFAULT.fill, // (ต้องมี fill เสมอ)
    border: STYLE_BORDER,
    alignment: STYLE_ALIGNMENT_RIGHT,
    numFmt: '0.00%'
};

const STYLE_GROWTH_RED: Partial<Style> = {
    font: { ...FONT_DEFAULT, color: { argb: 'FFFF0000' }, bold: true },
    fill: STYLE_DEFAULT.fill, // (ต้องมี fill เสมอ)
    border: STYLE_BORDER,
    alignment: STYLE_ALIGNMENT_RIGHT,
    numFmt: '0.00%'
};

// --- (แก้ไข) Status Styles - ระบุ Border ---
const STYLE_STATUS_SUCCESS_FILL: Partial<Style> = {
    font: { ...FONT_DEFAULT, color: { argb: 'FF006400' }, bold: true },
    fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFEBFBEF' } } as Fill,
    border: STYLE_BORDER, // <--- (แก้ไข) ระบุชัดเจน
    alignment: STYLE_ALIGNMENT_CENTER,
};
const STYLE_STATUS_ERROR_FILL: Partial<Style> = {
    font: { ...FONT_DEFAULT, color: { argb: 'FFB71C1C' }, bold: false },
    fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFEBEE' } } as Fill,
    border: STYLE_BORDER, // <--- (แก้ไข) ระบุชัดเจน
    alignment: STYLE_ALIGNMENT_CENTER,
};

// --- Border (ปรับปรุงใหม่) ---
// 1. สไตล์สำหรับ Header (เส้นขอบ 4 ด้านเหมือนเดิม)
const STYLE_FULL_BORDER: Partial<ExcelJS.Borders> = {
    top: { style: 'thin' as BorderStyle, color: { argb: 'FFBFBFBF' } }, // ทำให้สีอ่อนลง
    bottom: { style: 'thin' as BorderStyle, color: { argb: 'FFBFBFBF' } },
    left: { style: 'thin' as BorderStyle, color: { argb: 'FFBFBFBF' } },
    right: { style: 'thin' as BorderStyle, color: { argb: 'FFBFBFBF' } }
};

// 2. สไตล์สำหรับ Data (เน้นเส้นแนวนอน)
const STYLE_HORIZONTAL_BORDER: Partial<ExcelJS.Borders> = {
    // ไม่มี top, left, right
    bottom: { style: 'thin' as BorderStyle, color: { argb: 'FFEEEEEE' } } // สีเทาอ่อนมาก
};

// 3. สไตล์สำหรับแถว Total (มีเส้นขีดบนเพื่อแบ่งส่วน)
const STYLE_TOTAL_BORDER: Partial<ExcelJS.Borders> = {
    top: { style: 'thin' as BorderStyle, color: { argb: 'FFBFBFBF' } }, // เส้นบนสีเทา
    bottom: { style: 'thin' as BorderStyle, color: { argb: 'FFEEEEEE' } } // เส้นล่างสีเทาอ่อน
};

// --------------------------------------------------------------------
// 2. HELPER FUNCTION DEFINITIONS (ปรับปรุงความสูงแถวและ Number Format)
// --------------------------------------------------------------------

/**
 * (ใหม่)
 * Helper เพื่อดึง Number Format ที่ถูกต้อง
 */
const getNumFmt = (metricKey: string): string => {
    switch (metricKey) {
        case 'total_units':
            return '#,##0';
        case 'total_value':
        case 'total_area':
        case 'average_price_per_sqm':
            return '#,##0.00';
        default:
            return 'General';
    }
};

/**
 * (ปรับปรุง)
 * Sheet 1: ตารางอัตราการเติบโต
 */
const populateGrowthRateReportExport = (
    worksheet: Worksheet,
    data: any[],
    periods: PeriodItem[],
    showQoQValue: boolean,
    startRow: number
): number => {
    let currentRow = startRow;
    const periodsToDisplay = periods.filter((p: any) => p.key !== 'TOTAL_PERIODS');
    const lp = lastPeriod.value;
    const hasGrowth = lp && lp.monthIndex;
    const growthColCount = showQoQValue ? 4 : 3;
    
    if (!data || periodsToDisplay.length === 0) return currentRow;

    const metricsToInclude = [
        { key: 'total_units', name: 'จำนวนหลัง' },
        { key: 'total_value', name: 'มูลค่ารวม (บาท)' },
    ];
    const metricGroups = [...valueCategories, 'รวม'];

    // --- 1. Headers ---
    const rowHeader1 = worksheet.getRow(currentRow); 
    const rowHeader2 = worksheet.getRow(currentRow + 1);
    const rowHeader3 = worksheet.getRow(currentRow + 2);
    rowHeader1.height = 28; // เพิ่มความสูง
    rowHeader2.height = 28;
    rowHeader3.height = 28;

    rowHeader1.getCell(2).value = 'ช่วงมูลค่าบ้าน';
    rowHeader1.getCell(3).value = 'รายละเอียด';
    rowHeader1.getCell(4).value = `ข้อมูล ${chartSubtitle.value}`;
    if (hasGrowth) {
        rowHeader1.getCell(4 + periodsToDisplay.length).value = 'อัตราการเติบโต (สรุป)';
    }

    worksheet.mergeCells(currentRow, 2, currentRow + 2, 2); 
    worksheet.mergeCells(currentRow, 3, currentRow + 2, 3);
    worksheet.mergeCells(currentRow, 4, currentRow, 3 + periodsToDisplay.length);
    if (hasGrowth) {
        worksheet.mergeCells(currentRow, 4 + periodsToDisplay.length, currentRow, 3 + periodsToDisplay.length + growthColCount);
    }

    periodsToDisplay.forEach((p, i) => {
        rowHeader2.getCell(4 + i).value = p.label;
    });
    if (hasGrowth) {
        rowHeader2.getCell(4 + periodsToDisplay.length).value = `สรุป ณ ${lp.label}`;
        worksheet.mergeCells(currentRow + 1, 4 + periodsToDisplay.length, currentRow + 1, 3 + periodsToDisplay.length + growthColCount);
    }
    
    if (hasGrowth) {
        let growthColIdx = 4 + periodsToDisplay.length;
        rowHeader3.getCell(growthColIdx).value = 'MoM%';
        rowHeader3.getCell(growthColIdx + 1).value = 'YoY%';
        if (showQoQValue) {
            rowHeader3.getCell(growthColIdx + 2).value = 'QoQ%';
        }
        rowHeader3.getCell(growthColIdx + (showQoQValue ? 3 : 2)).value = 'YTD%';
    }
    
    [rowHeader1, rowHeader2, rowHeader3].forEach(r => {
        r.eachCell((cell, colIndex) => {
            if (colIndex > 1) cell.style = STYLE_HEADER as Style;
        });
    });

    currentRow += 3;

    // --- 2. Data Rows ---
    let dataRowIndex = 0;
    metricGroups.forEach((categoryName: any) => {
        const categoryData = data.find((c: any) => c.categoryName === categoryName);
        if (!categoryData) return;

        const isTotalGroup = categoryName === 'รวม';
        const startGroupRow = currentRow;

        metricsToInclude.forEach((metric, metricIndex) => {
            const row = worksheet.getRow(currentRow);
            row.height = 24; // เพิ่มความสูง

            const metricData = categoryData[metric.key];
            const rawData = categoryData[`${metric.key}_raw`];

            row.getCell(3).value = metric.name;
            
            periodsToDisplay.forEach((p, i) => {
                row.getCell(4 + i).value = rawData[p.key] || 0;
            });
            
            if (hasGrowth) {
                const growthData = metricData[lp.key];
                let growthColIdx = 4 + periodsToDisplay.length;
                
                row.getCell(growthColIdx).value = growthData?.MoM != null ? growthData.MoM / 100 : '-';
                row.getCell(growthColIdx + 1).value = growthData?.YoY != null ? growthData.YoY / 100 : '-';
                if (showQoQValue) {
                    row.getCell(growthColIdx + 2).value = growthData?.QoQ != null ? growthData.QoQ / 100 : '-';
                }
                row.getCell(growthColIdx + (showQoQValue ? 3 : 2)).value = growthData?.YTD != null ? growthData.YTD / 100 : '-';
            }

            // --- Apply Styles ---
            const baseFill = (dataRowIndex % 2 === 1 && !isTotalGroup) ? STYLE_BANDED_ROW.fill : STYLE_DEFAULT.fill;
            const baseStyle: Partial<Style> = isTotalGroup ? STYLE_PRIMARY_TOTAL : { ...STYLE_DEFAULT, fill: baseFill };
            
            row.eachCell((cell, colIndex) => {
                if (colIndex < 2) return;
                
                if (colIndex === 3) {
                    cell.style = { ...baseStyle, ...STYLE_GROUP_LEFT, font: {...baseStyle.font, ...STYLE_GROUP_LEFT.font} } as Style;
                } else if (colIndex >= 4 && colIndex < (4 + periodsToDisplay.length)) {
                    cell.style = { ...baseStyle, numFmt: getNumFmt(metric.key) } as Style; // ใช้ Format ที่ถูกต้อง
                } else if (colIndex >= (4 + periodsToDisplay.length)) {
                    const value = cell.value as number;
                    cell.style = { ...baseStyle, numFmt: '0.00%' } as Style;
                    if (value > 0) cell.style = { ...cell.style, ...STYLE_GROWTH_GREEN };
                    else if (value < 0) cell.style = { ...cell.style, ...STYLE_GROWTH_RED };
                }
            });

            currentRow++;
            dataRowIndex++;
        });

        // Merge Col A
        const groupRowCount = metricsToInclude.length;
        if (groupRowCount > 1) {
            worksheet.mergeCells(startGroupRow, 2, startGroupRow + groupRowCount - 1, 2);
        }
        const cellA = worksheet.getCell(startGroupRow, 2);
        cellA.value = categoryName;
        cellA.style = (isTotalGroup ? { ...STYLE_PRIMARY_TOTAL, ...STYLE_GROUP_LEFT } : STYLE_GROUP_LEFT) as Style;
        // ต้องกำหนด Fill ให้กับเซลล์ที่ถูก Merge ด้วย
        if (dataRowIndex % 2 === 1 && !isTotalGroup) {
             cellA.style.fill = STYLE_BANDED_ROW.fill;
        }
    });

    return currentRow - 1;
};

/**
 * (ปรับปรุง)
 * Sheet 2: สรุปสถานะสมาชิก
 */
const populateMemberSummaryReportExport = (
    worksheet: Worksheet,
    summary: any,
    startRow: number
): number => {
    let currentRow = startRow;
    const periods = summary.periodCounts;

    const header = ['รายละเอียด', 'จำนวนสมาชิก (คน)'];
    
    const data: any[][] = [
        ['สมาชิกทั้งหมด (ประเภท User)', summary.totalUsers],
        ['', ''], // แถวว่าง
        ['จำนวนสมาชิกที่กรอกในแต่ละช่วงเวลา:', ''],
        ...periods.map((p: any) => [`> ${p.label}`, p.count]),
        ['', ''], // แถวว่าง
        [summary.submittedLabel, summary.submittedTotal],
        [summary.notSubmittedLabel, summary.notSubmittedTotal]
    ];

    const redStyle: Partial<Style> = { ...STYLE_DEFAULT, font: { ...FONT_DEFAULT, color: { argb: 'FFFF0000' }, bold: true }, fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF8D7DA' } } as Fill };
    const boldStyle: Partial<Style> = { ...STYLE_DEFAULT, font: { ...FONT_TOTAL, bold: true }, alignment: STYLE_ALIGNMENT_LEFT };
    const totalRowStyle: Partial<Style> = { ...STYLE_TOTAL, font: FONT_TOTAL };

    // Write Header
    const headerRowSheet = worksheet.getRow(currentRow);
    headerRowSheet.height = 28; // เพิ่มความสูง
    headerRowSheet.getCell(2).value = header[0];
    headerRowSheet.getCell(3).value = header[1];
    headerRowSheet.getCell(2).style = STYLE_HEADER as Style;
    headerRowSheet.getCell(3).style = STYLE_HEADER as Style;
    currentRow++;

    // Write Data
    let dataRowIndex = 0; // สำหรับ Banding
    data.forEach((row: any, rowIndex: number) => {
        const excelRow = currentRow; // currentRow ถูกเพิ่มค่าไปแล้ว
        const newRow = worksheet.getRow(excelRow);
        newRow.height = 24; // เพิ่มความสูง

        const cellB = newRow.getCell(2);
        const cellC = newRow.getCell(3);
        cellB.value = row[0];
        cellC.value = row[1];
        
        const isLastRow = rowIndex === data.length - 1;
        const isTotalRow = rowIndex === data.length - 2;
        const isGroupHeader = rowIndex === 0 || rowIndex === 2;
        const isSpacer = rowIndex === 1 || rowIndex === 4;
        const isSubItem = row[0] && (row[0] as string).startsWith('>');
        
        let styleB: Partial<Style> = { ...STYLE_DEFAULT, alignment: STYLE_ALIGNMENT_LEFT };
        let styleC: Partial<Style> = { ...STYLE_DEFAULT, alignment: STYLE_ALIGNMENT_RIGHT, numFmt: '#,##0' };

        if (isLastRow) {
            styleB = { ...redStyle, alignment: STYLE_ALIGNMENT_LEFT };
            styleC = { ...redStyle, alignment: STYLE_ALIGNMENT_RIGHT, numFmt: '#,##0' };
        } else if (isTotalRow) {
            styleB = { ...totalRowStyle, alignment: STYLE_ALIGNMENT_LEFT };
            styleC = { ...totalRowStyle, alignment: STYLE_ALIGNMENT_RIGHT, numFmt: '#,##0' };
        } else if (isGroupHeader) {
            styleB = { ...boldStyle, font: { ...boldStyle.font, size: 16 } };
            styleC.font = FONT_DEFAULT;
        } else if (isSpacer) {
            styleB = { ...STYLE_DEFAULT, border: {} };
            styleC = { ...STYLE_DEFAULT, border: {} };
        } else if (isSubItem) {
             // (ใหม่) ใช้ Banding กับ Sub-items
             const baseFill = (dataRowIndex % 2 === 1) ? STYLE_BANDED_ROW.fill : STYLE_DEFAULT.fill;
             styleB.fill = baseFill;
             styleC.fill = baseFill;
             dataRowIndex++;
        }

        cellB.style = styleB as Style;
        cellC.style = cellC.value !== '' ? styleC as Style : { ...STYLE_DEFAULT, border: {} }; // กันไม่ให้ cell ว่างมี border
        
        currentRow++;
    });

    return currentRow - 1;
};

/**
 * (ปรับปรุง)
 * Sheet 3: สถานะการกรอกรายเดือน
 */
const populateMemberMonthlySubmissionExport = (
    worksheet: Worksheet,
    data: any[],
    periods: PeriodItem[],
    startRow: number
): number => {
    let currentRow = startRow;
    const periodsToDisplay = periods.filter((p: any) => p.key !== 'TOTAL_PERIODS');
    const periodHeaders = periodsToDisplay.map((p: any) => p.label);
    const headersCount = 2;
    const dataStartCol = 5; // Col E

    // --- Write Headers (Row 1 and 2) ---
    const row1 = worksheet.getRow(currentRow);
    const row2 = worksheet.getRow(currentRow + 1);
    row1.height = 28; // เพิ่มความสูง
    row2.height = 28;

    row1.getCell(2).value = 'รายชื่อสมาชิก';
    row1.getCell(3).value = 'ประเภท';
    row1.getCell(4).value = 'ยอดรวม';
    row1.getCell(dataStartCol).value = 'สถานะการกรอก';

    periodHeaders.forEach((label, i) => {
        row2.getCell(dataStartCol + i).value = label;
    });

    // --- Apply Merges (เหมือนเดิม) ---
    worksheet.mergeCells(currentRow, 2, currentRow + 1, 2);
    worksheet.mergeCells(currentRow, 3, currentRow + 1, 3);
    worksheet.mergeCells(currentRow, 4, currentRow + 1, 4);
    if (periodHeaders.length > 1) {
        worksheet.mergeCells(currentRow, dataStartCol, currentRow, dataStartCol + periodHeaders.length - 1);
    }
    
    [row1, row2].forEach(r => {
        r.eachCell((cell, colIndex) => {
            if (colIndex > 1) cell.style = STYLE_HEADER as Style;
        });
    });

    currentRow += headersCount;

    // --- (ปรับปรุง) Style Definitions for Data Rows ---
    const boldStyle: Partial<Style> = { ...STYLE_DEFAULT, font: { ...FONT_DEFAULT, bold: true }, alignment: STYLE_ALIGNMENT_LEFT };
    const totalStyle: Partial<Style> = { ...boldStyle, alignment: STYLE_ALIGNMENT_RIGHT, numFmt: '#,##0' };
    const leftStyle: Partial<Style> = { ...STYLE_DEFAULT, alignment: STYLE_ALIGNMENT_LEFT };

    // --- (ใหม่) Banding Styles ---
    const boldStyle_Banded: Partial<Style> = { ...boldStyle, fill: STYLE_BANDED_ROW.fill };
    const totalStyle_Banded: Partial<Style> = { ...totalStyle, fill: STYLE_BANDED_ROW.fill };
    const leftStyle_Banded: Partial<Style> = { ...leftStyle, fill: STYLE_BANDED_ROW.fill };
    const success_Banded: Partial<Style> = { ...STYLE_STATUS_SUCCESS_FILL, fill: STYLE_BANDED_ROW.fill };
const error_Banded: Partial<Style> = { ...STYLE_STATUS_ERROR_FILL, fill: STYLE_BANDED_ROW.fill };


    // Write Data
    data.forEach((m: any, rowIndex: number) => {
        const newRow = worksheet.getRow(currentRow);
        newRow.height = 24; // เพิ่มความสูง
        
        const isBanded = rowIndex % 2 === 1; // (ใหม่) ตรวจสอบแถบสี

        // Col B (Name)
        newRow.getCell(2).value = m.name;
        newRow.getCell(2).style = (isBanded ? boldStyle_Banded : boldStyle) as Style;
        
        // Col C (Type)
        newRow.getCell(3).value = m.member_type;
        newRow.getCell(3).style = (isBanded ? leftStyle_Banded : leftStyle) as Style;
        
        // Col D (Total)
        newRow.getCell(4).value = m.total_submitted_in_period;
        newRow.getCell(4).style = (isBanded ? totalStyle_Banded : totalStyle) as Style;

        // Col E+ (Status)
        periodsToDisplay.forEach((p: any, i: number) => {
            const cell = newRow.getCell(dataStartCol + i);
            const value = m.submissions[p.key] || '-';
            cell.value = value;
            if (value === 'X') {
                cell.style = (isBanded ? success_Banded : STYLE_STATUS_SUCCESS_FILL) as Style; // (ปรับปรุง)
            } else {
                cell.style = (isBanded ? error_Banded : STYLE_STATUS_ERROR_FILL) as Style; // (ปรับปรุง)
            }
        });
        
        currentRow++;
    });

    return currentRow - 1;
};

/**
 * (ปรับปรุง)
 * Sheet 4 & 5: สรุปตามมูลค่าบ้าน / สรุปตามภูมิภาค
 */
const populateSimpleVerticalTable = (
    worksheet: Worksheet,
    tableData: TableCategory[],
    periods: PeriodItem[],
    startRow: number,
    groupHeaderName: string
): number => {
    let currentRow = startRow;
    const lp = lastPeriod.value;
    const hasGrowth = lp && lp.monthIndex !== undefined;

    // --- 1. Headers ---
    const row1 = worksheet.getRow(currentRow);
    const row2 = worksheet.getRow(currentRow + 1);
    row1.height = 28; // เพิ่มความสูง
    row2.height = 28;

    const dataStartCol = 4; // Col D

    // Header Row 1
    row1.getCell(2).value = groupHeaderName;
    row1.getCell(3).value = 'รายละเอียด';
    row1.getCell(dataStartCol).value = `ข้อมูล ${chartSubtitle.value}`;
    if (hasGrowth) {
        row1.getCell(dataStartCol + periods.length).value = `อัตราเติบโต (ณ ${lp.label})`;
    }

    // Header Row 2
    periods.forEach((p, i) => {
        row2.getCell(dataStartCol + i).value = p.label;
    });
    if (hasGrowth) {
        row2.getCell(dataStartCol + periods.length).value = 'MoM%';
        row2.getCell(dataStartCol + periods.length + 1).value = 'YTD%';
    }

    // Merges
    worksheet.mergeCells(currentRow, 2, currentRow + 1, 2);
    worksheet.mergeCells(currentRow, 3, currentRow + 1, 3);
    worksheet.mergeCells(currentRow, dataStartCol, currentRow, dataStartCol + periods.length - 1);
    if (hasGrowth) {
        worksheet.mergeCells(currentRow, dataStartCol + periods.length, currentRow, dataStartCol + periods.length + 1);
    }
    
    // Style
    [row1, row2].forEach(r => {
        r.eachCell((cell, colIndex) => {
            if (colIndex > 1) cell.style = STYLE_HEADER as Style;
        });
        periods.forEach((p, i) => {
            row2.getCell(dataStartCol + i).style = { ...STYLE_HEADER, alignment: STYLE_ALIGNMENT_RIGHT } as Style;
        });
    });

    currentRow += 2;

    // --- 2. Data Rows ---
    let dataRowIndex = 0; // (ใหม่) สำหรับ Banding
    tableData.forEach((category: TableCategory) => {
        const startGroupRow = currentRow;
        const groupRowCount = category.rows.length;
        const isTotalGroup = category.categoryName === 'รวม' || category.categoryName === 'รวมทั่วประเทศ';

        category.rows.forEach((row: TableRow, rowIndex: number) => {
            const dataRow = worksheet.getRow(currentRow);
            dataRow.height = 24; // เพิ่มความสูง
            
            const isTotalMetric = row.metricKey === 'total_value';
            
            // (ใหม่) กำหนด Style พื้นฐาน (สำหรับ Banding)
            const baseFill = (dataRowIndex % 2 === 1 && !isTotalGroup) ? STYLE_BANDED_ROW.fill : STYLE_DEFAULT.fill;
            let baseStyle: Partial<Style> = isTotalGroup 
                ? (isTotalMetric ? STYLE_PRIMARY_TOTAL : STYLE_TOTAL) 
                : { ...STYLE_DEFAULT, fill: baseFill };

            // Col C (รายละเอียด)
            dataRow.getCell(3).value = row.metricName;
            dataRow.getCell(3).style = { ...baseStyle, alignment: STYLE_ALIGNMENT_LEFT } as Style;
            
            // Col D+ (Periods Data)
            periods.forEach((p: PeriodItem, i: number) => {
                const cell = dataRow.getCell(dataStartCol + i);
                const value = row.data[p.key] || 0;
                cell.value = value;
                cell.style = { ...baseStyle, numFmt: getNumFmt(row.metricKey) } as Style; // (ปรับปรุง)
                if (p.key === 'TOTAL_PERIODS') cell.style.font = { ...cell.style.font, bold: true };
            });
            
            // Col... (Growth Data)
            if (hasGrowth) {
                const cellMoM = dataRow.getCell(dataStartCol + periods.length);
                const cellYTD = dataRow.getCell(dataStartCol + periods.length + 1);
                
                cellMoM.value = row.growth.mom != null ? row.growth.mom / 100 : '-';
                cellYTD.value = row.growth.ytd != null ? row.growth.ytd / 100 : '-';

                [cellMoM, cellYTD].forEach(cell => {
                    cell.style = { ...baseStyle, numFmt: '0.00%' } as Style;
                    const value = cell.value as number;
                    if (value > 0) cell.style = { ...cell.style, ...STYLE_GROWTH_GREEN };
                    else if (value < 0) cell.style = { ...cell.style, ...STYLE_GROWTH_RED };
                });
            }
            
            currentRow++;
            if (!isTotalGroup) dataRowIndex++; // (ใหม่) นับ Index
        });

        // Merge Col B (Group)
        if (groupRowCount > 1) {
            worksheet.mergeCells(startGroupRow, 2, startGroupRow + groupRowCount - 1, 2);
        }
        const cellB = worksheet.getCell(startGroupRow, 2);
        cellB.value = category.categoryName;
      cellB.style = (isTotalGroup ? { ...STYLE_PRIMARY_TOTAL, ...STYLE_GROUP_LEFT } : STYLE_GROUP_LEFT) as Style;
    });

    return currentRow - 1;
};


/**
 * (ปรับปรุง)
 * Sheet 6: สรุปตามภูมิภาค และมูลค่าบ้าน
 */
const populateComplexVerticalTable = (
    worksheet: Worksheet,
    tableData: RegionCategoryGroup[],
    periods: PeriodItem[],
    startRow: number
): number => {
    let currentRow = startRow;
    const lp = lastPeriod.value;
    const hasGrowth = lp && lp.monthIndex !== undefined;

    // --- 1. Headers ---
    const row1 = worksheet.getRow(currentRow);
    const row2 = worksheet.getRow(currentRow + 1);
    row1.height = 28; // เพิ่มความสูง
    row2.height = 28;

    const dataStartCol = 5; // Col E

    // Header Row 1
    row1.getCell(2).value = 'ภูมิภาค';
    row1.getCell(3).value = 'ช่วงมูลค่าบ้าน';
    row1.getCell(4).value = 'รายละเอียด';
    row1.getCell(dataStartCol).value = `ข้อมูล ${chartSubtitle.value}`;
    if (hasGrowth) {
        row1.getCell(dataStartCol + periods.length).value = `อัตราเติบโต (ณ ${lp.label})`;
    }

    // Header Row 2
    periods.forEach((p, i) => {
        row2.getCell(dataStartCol + i).value = p.label;
    });
    if (hasGrowth) {
        row2.getCell(dataStartCol + periods.length).value = 'MoM%';
        row2.getCell(dataStartCol + periods.length + 1).value = 'YTD%';
    }

    // Merges
    worksheet.mergeCells(currentRow, 2, currentRow + 1, 2);
    worksheet.mergeCells(currentRow, 3, currentRow + 1, 3);
    worksheet.mergeCells(currentRow, 4, currentRow + 1, 4);
    worksheet.mergeCells(currentRow, dataStartCol, currentRow, dataStartCol + periods.length - 1);
    if (hasGrowth) {
        worksheet.mergeCells(currentRow, dataStartCol + periods.length, currentRow, dataStartCol + periods.length + 1);
    }
    
    // Style
    [row1, row2].forEach(r => {
        r.eachCell((cell, colIndex) => {
            if (colIndex > 1) cell.style = STYLE_HEADER as Style;
        });
        periods.forEach((p, i) => {
            row2.getCell(dataStartCol + i).style = { ...STYLE_HEADER, alignment: STYLE_ALIGNMENT_RIGHT } as Style;
        });
    });

    currentRow += 2;

    // --- 2. Data Rows ---
    let dataRowIndex = 0; // (ใหม่) สำหรับ Banding
    tableData.forEach((regionGroup: RegionCategoryGroup) => {
        const startRegionRow = currentRow;
        const isTotalRegion = regionGroup.regionName === 'รวมทั่วประเทศ';
        
        let totalRowsInRegion = 0;
        regionGroup.categories.forEach(cat => totalRowsInRegion += cat.rows.length);

        regionGroup.categories.forEach((category: TableCategory, categoryIndex: number) => {
            const startCategoryRow = currentRow;
            const groupRowCount = category.rows.length;
            const isTotalCategory = category.categoryName === 'รวม';

            category.rows.forEach((row: TableRow, rowIndex: number) => {
                const dataRow = worksheet.getRow(currentRow);
                dataRow.height = 24; // เพิ่มความสูง

                const isTotalMetric = row.metricKey === 'total_value';
                
                // (ใหม่) กำหนด Style พื้นฐาน (สำหรับ Banding)
                const baseFill = (dataRowIndex % 2 === 1 && !isTotalRegion && !isTotalCategory) ? STYLE_BANDED_ROW.fill : STYLE_DEFAULT.fill;
                const baseStyle: Partial<Style> = (isTotalRegion || isTotalCategory) 
                    ? (isTotalMetric ? STYLE_PRIMARY_TOTAL : STYLE_TOTAL) 
                    : { ...STYLE_DEFAULT, fill: baseFill };

                // Col D (รายละเอียด)
                dataRow.getCell(4).value = row.metricName;
                dataRow.getCell(4).style = { ...baseStyle, alignment: STYLE_ALIGNMENT_LEFT } as Style;

                // Col E+ (Periods Data)
                periods.forEach((p: PeriodItem, i: number) => {
                    const cell = dataRow.getCell(dataStartCol + i);
                    const value = row.data[p.key] || 0;
                    cell.value = value;
                    cell.style = { ...baseStyle, numFmt: getNumFmt(row.metricKey) } as Style; // (ปรับปรุง)
                    if (p.key === 'TOTAL_PERIODS') cell.style.font = { ...cell.style.font, bold: true };
                });
                
                // Col... (Growth Data)
                if (hasGrowth) {
                    const cellMoM = dataRow.getCell(dataStartCol + periods.length);
                    const cellYTD = dataRow.getCell(dataStartCol + periods.length + 1);
                    
                    cellMoM.value = row.growth.mom != null ? row.growth.mom / 100 : '-';
                    cellYTD.value = row.growth.ytd != null ? row.growth.ytd / 100 : '-';

                    [cellMoM, cellYTD].forEach(cell => {
                        cell.style = { ...baseStyle, numFmt: '0.00%' } as Style;
                        const value = cell.value as number;
                        if (value > 0) cell.style = { ...cell.style, ...STYLE_GROWTH_GREEN };
                        else if (value < 0) cell.style = { ...cell.style, ...STYLE_GROWTH_RED };
                    });
                }
                
                currentRow++;
                if (!isTotalCategory && !isTotalRegion) dataRowIndex++; // (ใหม่) นับ Index
            });

            // Merge Col C (Category)
            if (groupRowCount > 1) {
                worksheet.mergeCells(startCategoryRow, 3, startCategoryRow + groupRowCount - 1, 3);
            }
            const cellC = worksheet.getCell(startCategoryRow, 3);
            cellC.value = category.categoryName;
            cellC.style = (isTotalCategory || isTotalRegion ? { ...STYLE_PRIMARY_TOTAL, ...STYLE_GROUP_LEFT } : STYLE_GROUP_LEFT) as Style;
        });

        // Merge Col B (Region)
        if (totalRowsInRegion > 1) {
            worksheet.mergeCells(startRegionRow, 2, startRegionRow + totalRowsInRegion - 1, 2);
        }
        const cellB = worksheet.getCell(startRegionRow, 2);
        cellB.value = regionGroup.regionName;
        cellB.style = (isTotalRegion ? { ...STYLE_PRIMARY_TOTAL, ...STYLE_GROUP_LEFT } : STYLE_GROUP_LEFT) as Style;
    });

    return currentRow - 1;
};


// --------------------------------------------------------------------
// 3. MAIN EXPORT FUNCTION (ปรับปรุง Title และ Timestamp)
// --------------------------------------------------------------------
const exportToExcel = async () => {
    const workbook = new ExcelJS.Workbook();
    
    const chartSubtitleText = chartSubtitle.value || 'รายงานรวมตามปีปัจจุบัน';
    
    const allTablePeriods = tablePeriods.value || [];
    const allGrowthRateData = growthRateReportTableData.value;
    const allMemberSummary = memberSubmissionSummary.value;
    const allMemberMonthlyData = memberMonthlySubmissionTableData.value;
    const allMonthlyReportData = monthlyReportTableData.value;
    const allRegionReportData = regionReportTableData.value;
    const allRegionAndCategoryData = regionAndCategoryReportTableData.value;
    const showQoQValue = showQoQ.value;

    const sheetConfigurations = [
        { name: '1. อัตราเติบโต (มูลค่าบ้าน)', data: allGrowthRateData, periods: allTablePeriods, populateFn: populateGrowthRateReportExport },
        { name: '2. สรุปสถานะสมาชิก', data: allMemberSummary, periods: [], populateFn: populateMemberSummaryReportExport },
        { name: '3. สถานะการกรอกรายเดือน', data: allMemberMonthlyData, periods: allTablePeriods, populateFn: populateMemberMonthlySubmissionExport },
        { name: '4. สรุปตามมูลค่าบ้าน', data: allMonthlyReportData, periods: allTablePeriods, populateFn: populateSimpleVerticalTable, groupHeaderName: 'มูลค่าบ้าน' },
        { name: '5. สรุปตามภูมิภาค', data: allRegionReportData, periods: allTablePeriods, populateFn: populateSimpleVerticalTable, groupHeaderName: 'ภูมิภาค' },
        { name: '6. สรุปตามภูมิภาคและมูลค่า', data: allRegionAndCategoryData, periods: allTablePeriods, populateFn: populateComplexVerticalTable },
    ];

    // --- (ปรับปรุง) Title Styles ---
    const defaultTitleStyle: Partial<Style> = { 
        font: { name: COMMON_FONT_NAME, size: 22, bold: true, color: { argb: "FF3F51B5" } }, 
        alignment: STYLE_ALIGNMENT_CENTER 
    };
    const defaultSubtitleStyle: Partial<Style> = { 
        font: { name: COMMON_FONT_NAME, size: 18, bold: true, color: { argb: "FF000000" } }, 
        alignment: STYLE_ALIGNMENT_CENTER 
    };
    // --- (ใหม่) Timestamp Style ---
    const timestampStyle: Partial<Style> = { 
        font: { name: COMMON_FONT_NAME, size: 12, italic: true, color: { argb: "FF6C757D" } }, 
        alignment: STYLE_ALIGNMENT_CENTER 
    };


    for (const config of sheetConfigurations) {
        if (!config.data) continue;

        const worksheet = workbook.addWorksheet(config.name.substring(0, 31));
        const reportTitle = `รายงานยอดเซ็นสัญญา: ${config.name.replace(/^[0-9]\. /g, '').replace(/\(.+\)/g, '').trim()}`;
        const subTitle = chartSubtitleText.startsWith('กรุณา') ? 'รายงานรวมตามปีปัจจุบัน' : chartSubtitleText;
        
        // (ใหม่) สร้าง Timestamp
        const now = new Date();
        const generatedOn = `จัดทำเมื่อ: ${now.toLocaleDateString('th-TH', { year: 'numeric', month: 'long', day: 'numeric' })} เวลา ${now.toLocaleTimeString('th-TH')}`;

        // (ปรับปรุง) การคำนวณ Max Columns (ถูกต้องแล้วจากรอบที่แล้ว)
        const periodsData = config.periods || [];
        const periodsToDisplay = periodsData.filter((p: any) => p.key !== 'TOTAL_PERIODS');
        const lp = periodsToDisplay.length > 0 ? periodsToDisplay[periodsToDisplay.length - 1] : (allTablePeriods[0] || null);
        const hasGrowth = lp && lp.monthIndex !== undefined;
        let maxCols: number;
        if (config.name.includes('อัตราเติบโต')) {
            maxCols = 3 + periodsToDisplay.length + (hasGrowth ? (showQoQValue ? 4 : 3) : 0);
        } else if (config.name.includes('สถานะสมาชิก')) {
            maxCols = 3;
        } else if (config.name.includes('สถานะการกรอก')) {
            maxCols = 4 + periodsToDisplay.length;
        } else if (config.name.includes('สรุปตามภูมิภาคและมูลค่า')) {
            maxCols = 4 + periodsData.length + (hasGrowth ? 2 : 0);
        } else if (config.name.includes('สรุปตามมูลค่าบ้าน') || config.name.includes('สรุปตามภูมิภาค')) {
            maxCols = 3 + periodsData.length + (hasGrowth ? 2 : 0);
        } else {
            maxCols = 10; // Fallback
        }

        // 1. Write and Merge Title/Subtitle/Timestamp
        worksheet.insertRow(1, [reportTitle]);
        worksheet.insertRow(2, [subTitle]);
        worksheet.insertRow(3, [generatedOn]); // (ใหม่)
        worksheet.insertRow(4, []); // Blank Row

        const safeMaxCols = Math.max(1, maxCols); 
        worksheet.mergeCells(1, 1, 1, safeMaxCols);
        worksheet.mergeCells(2, 1, 2, safeMaxCols);
        worksheet.mergeCells(3, 1, 3, safeMaxCols); // (ใหม่)

        // Apply Title/Subtitle/Timestamp Style
        worksheet.getCell(1, 1).style = defaultTitleStyle as Style;
        worksheet.getCell(2, 1).style = defaultSubtitleStyle as Style;
        worksheet.getCell(3, 1).style = timestampStyle as Style; // (ใหม่)
        worksheet.getRow(1).height = 28;
        worksheet.getRow(2).height = 24;
        worksheet.getRow(3).height = 18;


        // 2. Populate Data and Apply Column Widths
        let columns: Partial<Column>[] = [{ width: 5 }]; // Col A (Spacer)
        // ... (ส่วน Column Widths เหมือนเดิม ไม่ต้องแก้ไข) ...
        if (config.name.includes('อัตราเติบโต')) {
            columns.push({ width: 30 }, { width: 40 }); // B, C
            periodsToDisplay.forEach(() => columns.push({ width: 18 }));
            if(hasGrowth) {
                columns.push({ width: 12 }, { width: 12 });
                if(showQoQValue) columns.push({ width: 12 });
                columns.push({ width: 12 });
            }
        } else if (config.name.includes('สถานะสมาชิก')) {
            columns.push({ width: 45 }, { width: 20 }); // B, C
        } else if (config.name.includes('สถานะการกรอก')) {
            columns.push({ width: 30 }, { width: 20 }, { width: 15 }); // B, C, D
            periodsToDisplay.forEach(() => columns.push({ width: 10 }));
        } else if (config.name.includes('สรุปตามภูมิภาคและมูลค่า')) {
            columns.push({ width: 30 }, { width: 25 }, { width: 25 }); // B, C, D
            periodsData.forEach(() => columns.push({ width: 15 }));
            if (hasGrowth) { columns.push({ width: 12 }, { width: 12 }); }
        } else { // Sheet 4, 5
            columns.push({ width: 30 }, { width: 25 }); // B, C
            periodsData.forEach(() => columns.push({ width: 15 }));
            if (hasGrowth) { columns.push({ width: 12 }, { width: 12 }); }
        }
        worksheet.columns = columns as Column[];


        // --- Populate Data ---
        // (ใช้ Type Assertion เพื่อเรียกฟังก์ชันที่ถูกต้อง)
        if (config.populateFn === populateGrowthRateReportExport) {
            (config.populateFn as Function)(worksheet, config.data, config.periods, showQoQValue, TITLE_ROWS + 1);
        } else if (config.populateFn === populateSimpleVerticalTable) {
            (config.populateFn as Function)(worksheet, config.data, config.periods, TITLE_ROWS + 1, config.groupHeaderName);
        } else if (config.populateFn === populateComplexVerticalTable) {
            (config.populateFn as Function)(worksheet, config.data, config.periods, TITLE_ROWS + 1);
        } else if (config.populateFn === populateMemberSummaryReportExport) {
            (config.populateFn as Function)(worksheet, config.data, TITLE_ROWS + 1);
        } else if (config.populateFn === populateMemberMonthlySubmissionExport) {
            (config.populateFn as Function)(worksheet, config.data, config.periods, TITLE_ROWS + 1);
        }
    }

    // 4. สร้างไฟล์และดาวน์โหลด
    const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
    const filename = `HBA_Report_${dateStr}.xlsx`;

    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
};

//PDF

const blobToBase64 = (blob: Blob): Promise<string> => {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onloadend = () => {
            const dataUrl = reader.result as string;
            // remove the prefix 'data:application/octet-stream;base64,'
            const base64 = dataUrl.split(',')[1];
            resolve(base64);
        };
        reader.onerror = reject;
        reader.readAsDataURL(blob);
    });
};
const addThaiFont = async (doc: jsPDF) => {
    const fontUrl = '/fonts/THSarabunNew.ttf'; // <-- นี่คือ URL ที่คุณระบุ

    try {
        // 1. Fetch the font file from your public folder
        const response = await fetch(fontUrl);
        if (!response.ok) {
            throw new Error(`Failed to fetch font: ${response.statusText}`);
        }
        const fontBlob = await response.blob();

        // 2. Convert to Base64
        const thSarabunBase64 = await blobToBase64(fontBlob);

        if (!thSarabunBase64 || thSarabunBase64.length < 1000) {
             throw new Error("Fetched font data is invalid or empty.");
        }

        // 3. Add to jsPDF
        doc.addFileToVFS("THSarabunNew.ttf", thSarabunBase64);
        // ใช้ชื่อฟอนต์ที่ลงทะเบียนเป็น "THSarabunNew"
        doc.addFont("THSarabunNew.ttf", "THSarabunNew", "normal"); 
        doc.setFont("THSarabunNew");
        console.log("Thai font loaded successfully from URL.");
        return true;

    } catch (e) {
        console.error("[PDF Export] Error loading/adding Thai font from URL:", e);
        doc.setFont("helvetica"); // Fallback
        return false;
    }
};

// Helper function สำหรับวาดหัวข้อและจัดการการขึ้นหน้าใหม่
let cursorY = 0;
const pageHeight = 595; // A4 landscape height in px (approx)
const pageWidth = 842; // A4 landscape width in px (approx)
const margin = 30;

const pdfNewPage = (doc: jsPDF) => {
    doc.addPage();
    cursorY = margin;
};

const pdfCheckAddPage = (doc: jsPDF, heightNeeded: number) => {
    if (cursorY + heightNeeded > pageHeight - margin) {
        pdfNewPage(doc);
    }
};

const pdfAddTitle = (doc: jsPDF, title: string) => {
    pdfCheckAddPage(doc, 30);
    doc.setFontSize(16);
    doc.text(title, margin, cursorY);
    cursorY += 20;
};

// Helper function สำหรับจับภาพ Chart
const pdfAddChart = async (doc: jsPDF, elementId: string) => {
    const chartEl = document.getElementById(elementId);
    if (!chartEl) {
        console.warn(`[PDF Export] Chart element not found: #${elementId}`);
        return;
    }

    try {
        const canvas = await html2canvas(chartEl, { useCORS: true, scale: 2 });
        const imgData = canvas.toDataURL('image/png');

        const imgWidth = pageWidth - (margin * 2);
        const imgHeight = (canvas.height * imgWidth) / canvas.width;

        pdfCheckAddPage(doc, imgHeight + 10); // +10 for padding

        doc.addImage(imgData, 'PNG', margin, cursorY, imgWidth, imgHeight);
        cursorY += imgHeight + 20; // Add padding after chart
    } catch (e) {
        console.error(`Error capturing chart #${elementId}:`, e);
    }
};

// Helper function สำหรับวาดตาราง (ตัวอย่างแบบง่าย)
const pdfAddSimpleTable = (doc: jsPDF, head: any[], body: any[], isMemberTable: boolean = false) => {
    pdfCheckAddPage(doc, 40); // Space for header

    autoTable(doc, {
        head: head,
        body: body,
        startY: cursorY,
        theme: 'grid',
        styles: {
            font: "THSarabunNew", // *** [FIX] FONT BODY ***
            fontSize: 10,
            cellPadding: 2,
        },
        headStyles: {
            font: "THSarabunNew", // *** [FIX] FONT HEADER ***
            fillColor: [41, 128, 185], // สีน้ำเงิน
            textColor: 255,
            fontSize: 12,
        },
        bodyStyles: {
            // กำหนดสีตัวอักษรสำหรับตารางสมาชิก
            textColor: isMemberTable ? [41, 128, 185] : 0, // สีน้ำเงิน
        },
        didDrawPage: (data) => {
            // อัปเดต cursorY หลังจาก autoTable วาดเสร็จ (รวมถึงการขึ้นหน้าใหม่)
            cursorY = data.cursor ? data.cursor.y + 10 : margin;
        }
    });
    
    // [FIX] แก้ไข TypeError: Cannot read properties of undefined (reading 'previous')
    const prevTable = (doc as any).autoTable?.previous;

    if (prevTable) {
        cursorY = prevTable.finalY + 20; // อัปเดต Y โดยใช้ค่า finalY ที่แน่นอนของตารางล่าสุด + 20px padding
    } else {
        cursorY += 20;
    }
};

// [NEW HELPER] Helper สำหรับวาดตารางสรุปสถานะสมาชิก (ตารางด้านข้าง)
const pdfAddMemberSummaryTable = (doc: jsPDF, summary: any) => {
    pdfCheckAddPage(doc, 60);

    const body: any[] = [
        ['สมาชิกทั้งหมด (ประเภท User)', { content: summary.totalUsers.toLocaleString('th-TH') + ' คน', styles: { fontStyle: 'bold' } }],
        [{ content: 'จำนวนสมาชิกที่กรอกในแต่ละช่วงเวลา:', colSpan: 2, styles: { fontStyle: 'bold', fillColor: [240, 240, 240] } }],
        ...summary.periodCounts.map((p: any) => [
            p.label, 
            p.count.toLocaleString('th-TH') + ' คน'
        ]),
        [{ content: summary.submittedLabel, styles: { fontStyle: 'bold', fillColor: [220, 240, 220] } }, {content: summary.submittedTotal.toLocaleString('th-TH') + ' คน', styles: { fillColor: [220, 240, 220] }}],
        [{ content: summary.notSubmittedLabel, styles: { fontStyle: 'bold', textColor: [180, 0, 0], fillColor: [255, 230, 230] } }, { content: summary.notSubmittedTotal.toLocaleString('th-TH') + ' คน', styles: { fontStyle: 'bold', textColor: [180, 0, 0], fillColor: [255, 230, 230] } }]
    ];

    autoTable(doc, {
        head: [['รายละเอียด', 'จำนวนสมาชิก (คน)']],
        body: body,
        startY: cursorY,
        theme: 'grid',
        styles: {
            font: "THSarabunNew", // *** [FIX] FONT BODY ***
            fontSize: 10,
            cellPadding: 2,
            halign: 'left'
        },
        headStyles: {
            font: "THSarabunNew", // *** [FIX] FONT HEADER ***
            fillColor: [41, 128, 185],
            textColor: 255,
            fontSize: 12,
        },
        didDrawPage: (data) => {
            cursorY = data.cursor ? data.cursor.y + 10 : margin;
        }
    });

    const prevTable = (doc as any).autoTable?.previous;
    if (prevTable) {
        cursorY = prevTable.finalY + 20;
    } else {
        cursorY += 20;
    }
};

// [NEW HELPER] Helper สำหรับวาดตารางสถานะการกรอกรายเดือน (แยกตามรายสมาชิก)
const pdfAddMemberSubmissionTable = (doc: jsPDF, data: any[]) => {
    pdfCheckAddPage(doc, 40);

    const periods = tablePeriods.value.filter(p => p.key !== 'TOTAL_PERIODS');
    const periodsToDisplay = periods.map(p => p.label);
    
    // Headers (RowSpan 2)
    const head = [
        [{ content: 'รายชื่อสมาชิก', rowSpan: 2 }, { content: 'ประเภท', rowSpan: 2 }, { content: 'ยอดรวม', rowSpan: 2 }, { content: 'สถานะการกรอก', colSpan: periodsToDisplay.length }],
        periodsToDisplay
    ];

    const body: any[] = data.map(member => {
        const rowData: any[] = [
            member.name,
            member.member_type,
            { content: member.total_submitted_in_period.toLocaleString('th-TH'), styles: { halign: 'center', fontStyle: 'bold' } }
        ];
        periods.forEach(p => {
            const status = member.submissions[p.key] || '-';
            rowData.push({
                content: status,
                styles: { 
                    textColor: status === 'X' ? [0, 100, 0] : [180, 0, 0], // Green for X, Red for -
                    fontStyle: status === 'X' ? 'bold' : 'normal',
                    halign: 'center'
                }
            });
        });
        return rowData;
    });

    autoTable(doc, {
        head: head,
        body: body,
        startY: cursorY,
        theme: 'grid',
        styles: {
            font: "THSarabunNew", // *** [FIX] FONT BODY ***
            fontSize: 9,
            cellPadding: 2,
            halign: 'left'
        },
        headStyles: {
            font: "THSarabunNew", // *** [FIX] FONT HEADER ***
            fillColor: [41, 128, 185],
            textColor: 255,
            fontSize: 11,
            halign: 'center'
        },
        didDrawPage: (data) => {
            cursorY = data.cursor ? data.cursor.y + 10 : margin;
        }
    });

    const prevTable = (doc as any).autoTable?.previous;
    if (prevTable) {
        cursorY = prevTable.finalY + 20;
    } else {
        cursorY += 20;
    }
};

// [NEW HELPER] Helper สำหรับวาดตารางสรุปตามภูมิภาคและมูลค่าบ้าน (Complex Table)
const pdfAddComplexRegionTable = (doc: jsPDF, data: RegionCategoryGroup[]) => {
    pdfCheckAddPage(doc, 40);

    const periods = tablePeriods.value;
    const periodsToDisplay = periods.map(p => p.label);
    
    // Headers
    const head = [
        ['ภูมิภาค', 'ช่วงมูลค่าบ้าน', 'รายละเอียด', ...periodsToDisplay, 'MoM%', 'YTD%']
    ];

    const body: any[] = [];
    const getNumFmt = (metricKey: string, v: number) => {
        if (metricKey === 'total_units') return v.toLocaleString('th-TH', { maximumFractionDigits: 0 });
        return v.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    data.forEach(regionGroup => {
        let isFirstRegionRow = true;
        const totalRegionRows = regionGroup.categories.reduce((sum, cat) => sum + cat.rows.length, 0);

        regionGroup.categories.forEach(category => {
            let isFirstCategoryRow = true;
            const totalCategoryRows = category.rows.length;

            category.rows.forEach(row => {
                const rowData: any[] = [];

                // Col 1: Region (RowSpan)
                if (isFirstRegionRow) {
                    rowData.push({ content: regionGroup.regionName, rowSpan: totalRegionRows, styles: { fillColor: [240, 240, 255], fontStyle: 'bold' } });
                    isFirstRegionRow = false;
                }

                // Col 2: Category (RowSpan)
                if (isFirstCategoryRow) {
                    rowData.push({ content: category.categoryName, rowSpan: totalCategoryRows, styles: { fontStyle: 'bold' } });
                    isFirstCategoryRow = false;
                }

                // Col 3: Metric Name
                rowData.push(row.metricName);

                // Col 4+: Period Data
                periods.forEach(p => {
                    rowData.push({content: getNumFmt(row.metricKey, row.data[p.key] || 0), styles: {halign: 'right'}});
                });

                // Col ...: Growth
                rowData.push({ content: row.growth.mom != null ? `${row.growth.mom.toFixed(2)}%` : '-', styles: { halign: 'right', textColor: row.growth.mom ? (row.growth.mom > 0 ? [0, 100, 0] : [180, 0, 0]) : [0, 0, 0] } });
                rowData.push({ content: row.growth.ytd != null ? `${row.growth.ytd.toFixed(2)}%` : '-', styles: { halign: 'right', textColor: row.growth.ytd ? (row.growth.ytd > 0 ? [0, 100, 0] : [180, 0, 0]) : [0, 0, 0] } });

                body.push(rowData);
            });
        });
    });

    autoTable(doc, {
        head: head,
        body: body,
        startY: cursorY,
        theme: 'grid',
        styles: {
            font: "THSarabunNew", // *** [FIX] FONT BODY ***
            fontSize: 9, 
            cellPadding: 2,
        },
        headStyles: {
            font: "THSarabunNew", // *** [FIX] FONT HEADER ***
            fillColor: [41, 128, 185],
            textColor: 255,
            fontSize: 10,
        },
        didDrawCell: (data) => {
             // Highlight 'รวมทั่วประเทศ' rows and 'รวม' category
             if (data.row.raw[0]?.content === 'รวมทั่วประเทศ' || data.row.raw[1]?.content === 'รวม') {
                data.cell.styles.fillColor = [220, 230, 240];
             }
        },
        didDrawPage: (data) => {
            cursorY = data.cursor ? data.cursor.y + 10 : margin;
        }
    });

    const prevTable = (doc as any).autoTable?.previous;
    if (prevTable) {
        cursorY = prevTable.finalY + 20;
    } else {
        cursorY += 20;
    }
};

// ... (โค้ดทั้งหมดของคุณก่อนหน้านี้ เช่น blobToBase64, addThaiFont, pdfNewPage, pdfAddChart ฯลฯ) ...

const exportToPDF = async () => {
    isPdfLoading.value = true;
    try {
        const doc = new jsPDF({
            orientation: 'landscape', // --- [สำคัญ] แนวนอน ---
            unit: 'px',
            format: 'a4'
        });

        // --- 1. Add Thai Font ---
        const fontAdded = await addThaiFont(doc); 
        
        if (!fontAdded) {
            console.error("Stopping PDF generation due to font loading failure.");
            alert("เกิดข้อผิดพลาดในการโหลดฟอนต์ภาษาไทยสำหรับ PDF");
            isPdfLoading.value = false;
            return;
        }

        cursorY = margin; // Reset cursor

        // --- 2. Add Title ---
        doc.setFontSize(20);
        doc.text("รายงานเปรียบเทียบยอดเซ็นสัญญา", pageWidth / 2, cursorY, { align: 'center' });
        cursorY += 25;
        doc.setFontSize(14);
        doc.text(chartSubtitle.value, pageWidth / 2, cursorY, { align: 'center' });
        cursorY += 30;

        // --- 3. Add Sections (กราฟและตาราง) ---

        // === Section 1: กราฟเปรียบเทียบหลัก ===
        pdfAddTitle(doc, "1. กราฟเปรียบเทียบยอดเซ็นสัญญา (แยกตามมูลค่าบ้าน)");
        await pdfAddChart(doc, 'chart1');

        // === Section 2: กราฟสัดส่วน ===
        pdfAddTitle(doc, "2. กราฟสัดส่วนมูลค่ารวม");
        await pdfAddChart(doc, 'polarChartPrice');
        await pdfAddChart(doc, 'polarChartRegion');
        
        pdfNewPage(doc); // ขึ้นหน้าใหม่ก่อนเริ่มตาราง

        // === Section 3: ตารางสรุปตามมูลค่าบ้าน ===
        pdfAddTitle(doc, "3. ตารางสรุปยอดเซ็นสัญญา (แยกตามมูลค่าบ้าน)");
        if (monthlyReportTableData.value.length > 0) {
            // เตรียม Header สำหรับ autoTable
            const periods = tablePeriods.value.map(p => p.label);
            const head = [
                ['มูลค่าบ้าน', 'รายละเอียด', ...periods, 'MoM%', 'YTD%']
            ];
            
            // เตรียม Body
            const body: any[] = [];
            monthlyReportTableData.value.forEach(category => {
                category.rows.forEach((row, index) => {
                    const rowData: any[] = [];
                    // จัดการ RowSpan
                    if (index === 0) {
                        rowData.push({ content: category.categoryName, rowSpan: category.rows.length, styles: { halign: 'center', valign: 'middle' } });
                    }
                    rowData.push(row.metricName);
                    
                    // เพิ่มข้อมูลตาม Period
                    tablePeriods.value.forEach(p => {
                        rowData.push({content: row.format(row.data[p.key] || 0), styles: { halign: 'right' }});
                    });
                    
                    // เพิ่ม Growth
                    rowData.push({content: row.growth.mom != null ? `${row.growth.mom.toFixed(2)}%` : '-', styles: { halign: 'right', textColor: row.growth.mom ? (row.growth.mom > 0 ? [0, 100, 0] : [180, 0, 0]) : [0, 0, 0] } });
                    rowData.push({content: row.growth.ytd != null ? `${row.growth.ytd.toFixed(2)}%` : '-', styles: { halign: 'right', textColor: row.growth.ytd ? (row.growth.ytd > 0 ? [0, 100, 0] : [180, 0, 0]) : [0, 0, 0] } });
                    
                    body.push(rowData);
                });
            });
            
            pdfAddSimpleTable(doc, head, body);
        }

        // === Section 4: ตารางสรุปตามภูมิภาค (Region Summary Table) ===
        pdfAddTitle(doc, "4. ตารางสรุปยอดเซ็นสัญญา (แยกตามภูมิภาค)");
        if (regionReportTableData.value.length > 0) {
             const periods = tablePeriods.value.map(p => p.label);
            const head = [
                ['ภูมิภาค', 'รายละเอียด', ...periods, 'MoM%', 'YTD%']
            ];
             const body: any[] = [];
            regionReportTableData.value.forEach(category => {
                category.rows.forEach((row, index) => {
                    const rowData: any[] = [];
                    if (index === 0) {
                        rowData.push({ content: category.categoryName, rowSpan: category.rows.length, styles: { halign: 'center', valign: 'middle' } });
                    }
                    rowData.push(row.metricName);
                    tablePeriods.value.forEach(p => {
                        rowData.push({content: row.format(row.data[p.key] || 0), styles: { halign: 'right' }});
                    });
                    rowData.push({content: row.growth.mom != null ? `${row.growth.mom.toFixed(2)}%` : '-', styles: { halign: 'right', textColor: row.growth.mom ? (row.growth.mom > 0 ? [0, 100, 0] : [180, 0, 0]) : [0, 0, 0] } });
                    rowData.push({content: row.growth.ytd != null ? `${row.growth.ytd.toFixed(2)}%` : '-', styles: { halign: 'right', textColor: row.growth.ytd ? (row.growth.ytd > 0 ? [0, 100, 0] : [180, 0, 0]) : [0, 0, 0] } });
                    body.push(rowData);
                });
            });
             pdfAddSimpleTable(doc, head, body);
        }
        
        // === [NEW] Section 5: ตารางสรุปยอดเซ็นสัญญา แยกตามภูมิภาค และมูลค่าบ้าน (Complex Table) ===
        pdfAddTitle(doc, "5. ตารางสรุปยอดเซ็นสัญญา (แยกตามภูมิภาค และมูลค่าบ้าน)");
        if (regionAndCategoryReportTableData.value.length > 0) {
            pdfAddComplexRegionTable(doc, regionAndCategoryReportTableData.value);
        }

        pdfNewPage(doc); // ขึ้นหน้าใหม่สำหรับรายงานสมาชิก

        // === [NEW] Section 6: รายงานสถานะการกรอกสัญญาของสมาชิก (Summary Table & Graph) ===
        pdfAddTitle(doc, "6. รายงานสถานะการกรอกสัญญาของสมาชิก");

        // 6.1 ตารางสรุปสถานะการกรอก
        pdfAddTitle(doc, "6.1 ตารางสรุปจำนวนสมาชิกที่กรอก");
        if (memberSubmissionSummary.value.donutData.length > 0) {
            pdfAddMemberSummaryTable(doc, memberSubmissionSummary.value);
        }

        // 6.2 กราฟสถานะการกรอก
        pdfAddTitle(doc, "6.2 กราฟสถานะการกรอกสัญญา (จำแนกตามช่วงเวลาที่เลือก)");
        await pdfAddChart(doc, 'monthlyBarChartMember');
        
        pdfNewPage(doc);

        // === [NEW] Section 7: ตารางสถานะการกรอกสัญญาต่อเดือน (แยกตามรายสมาชิก) ===
        pdfAddTitle(doc, "7. ตารางสถานะการกรอกสัญญาต่อเดือน (แยกตามรายสมาชิก)");
        if (memberMonthlySubmissionTableData.value.length > 0) {
            pdfAddMemberSubmissionTable(doc, memberMonthlySubmissionTableData.value);
        }


        // --- 4. Save Document ---
        const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
        doc.save(`HBA_Report_PDF_${dateStr}.pdf`);

    } catch (error) {
        console.error("[PDF Export] Error generating PDF:", error);
        alert("เกิดข้อผิดพลาดระหว่างการสร้างไฟล์ PDF");
    } finally {
        isPdfLoading.value = false;
    }
};
</script>




<template>
    <v-app>
        <v-container>
            <v-row>
                <v-col cols="12">
                    <div class="mt-3 mb-6">
                        <div class="d-flex justify-space-between">
                            <div class="d-flex py-0 align-center">
                                <div>
                                    <h3 class="text-h5 card-title">รายงานเปรียบเทียบยอดเซ็นสัญญา</h3>
                                </div>
                            </div>
                           <v-col cols="auto">
                                <v-btn 
                                    color="success" 
                                    prepend-icon="mdi-microsoft-excel" 
                                    @click="exportToExcel"
                                    class="mr-2"
                                >
                                    Export Excel
                                </v-btn>
                                
                                <v-btn 
                                    color="error" 
                                    prepend-icon="mdi-file-pdf-box" 
                                    @click="exportToPDF"
                                    :loading="isPdfLoading"
                                    :disabled="isPdfLoading"
                                >
                                    Export PDF
                                </v-btn>
                                </v-col>
                        </div>
                    </div>
                </v-col>
            </v-row>

            <v-row>
                <v-col cols="12">
                    <v-card>
                        <v-card-text>

                            <v-tabs v-model="tab" color="primary" grow>
                                <v-tab value="monthly">
                                    <v-icon start>mdi-calendar-month</v-icon>
                                    เปรียบเทียบรายเดือน/ไตรมาส
                                </v-tab>
                            </v-tabs>

                            <v-window v-model="tab" class="pt-5">
                                <v-window-item value="monthly">
                                    <v-container fluid class="pa-0">
                                        <v-alert density="compact" variant="tonal" color="info" class="mb-4">
                                            <b>วิธีใช้งาน (การเลือกช่วงเวลา):</b><br />
                                            - <b>สรุปยอดรวมของปีปัจจุบัน:</b> หาก <b>ไม่ได้เลือก</b> ปี เดือน
                                            หรือไตรมาสใดๆ
                                            ระบบจะแสดงข้อมูลรวมของปีปัจจุบันตั้งแต่มกราคมจนถึงเดือนปัจจุบัน<br />
                                            - <b>เปรียบเทียบระหว่างเดือน/ไตรมาส (ในปีเดียวกัน):</b> ให้เลือก <b>1 ปี</b>
                                            และเลือก เดือน/ไตรมาส ที่ต้องการเปรียบเทียบได้หลายช่วง<br />
                                            - <b>เปรียบเทียบระหว่างปี (ในเดือน/ไตรมาสเดียวกัน):</b> ให้เลือก
                                            <b>หลายปี</b> และเลือก เดือน/ไตรมาส
                                            (ระบบจะเปรียบเทียบข้อมูลในช่วงเวลาที่เลือกของแต่ละปี)<br />
                                        </v-alert>

                                        <v-row>
                                            <v-col cols="12" md="4">
                                                <v-combobox v-model="selectyear" :items="year" label="เลือกปี" chips
                                                    multiple clearable variant="outlined"
                                                    density="comfortable"></v-combobox>
                                            </v-col>
                                            <v-col cols="12" md="4">
                                                <v-combobox v-model="selectQuarters"
                                                    :items="availableQuarters.map(q => q.name)" label="เลือกไตรมาส"
                                                    chips multiple clearable variant="outlined" density="comfortable">
                                                </v-combobox>
                                            </v-col>
                                            <v-col cols="12" md="4">
                                                <v-combobox v-model="selectMonths" :items="availableMonths"
                                                    label="เลือกเดือน" chips multiple clearable variant="outlined"
                                                    density="comfortable"></v-combobox>
                                            </v-col>
                                        </v-row>
                                    </v-container>
                                </v-window-item>
                            </v-window>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">ตารางอัตราการเติบโต (แยกตามมูลค่าบ้าน)
                            </h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>

                            <v-table density="compact" class="mt-4 border">
                                <thead>
                                    <tr>
                                        <th rowspan="3" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">ช่วงมูลค่าบ้าน</th>
                                        <th rowspan="3" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">รายละเอียด</th>
                                        <th :colspan="tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS').length"
                                            class="text-center text-h6 border-b-sm border-e">
                                            ข้อมูล {{ chartSubtitle }}</th>
                                        <th :colspan="showQoQ ? 4 : 3" class="text-center text-h6 border-b-sm">
                                            อัตราการเติบโต (สรุป)
                                        </th>
                                    </tr>
                                    <tr>
                                        <th v-for="(period, index) in tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS')"
                                            :key="`raw-${period.key}`"
                                            class="text-center text-subtitle-1 border-b-sm border-e"
                                            style="min-width: 120px;" :class="{
                                                'bg-blue-grey-lighten-5': !period.monthIndex,
                                            }">
                                            {{ period.label }}
                                        </th>

                                        <th :colspan="showQoQ ? 4 : 3"
                                            class="text-center text-h6 border-b-sm bg-blue-grey-lighten-5"
                                            v-if="lastPeriod">
                                            สรุป ณ {{ lastPeriod.label }}
                                        </th>
                                        <th v-else :colspan="showQoQ ? 4 : 3" class="text-center text-h6 border-b-sm">
                                            -
                                        </th>
                                    </tr>
                                    <tr>
                                        <td v-for="(period, index) in tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS')"
                                            :key="`empty-header-cell-${period.key}`" class="border-e"></td>

                                        <template v-if="lastPeriod">
                                            <th class="text-center text-subtitle-1" style="min-width: 80px;">MoM%</th>
                                            <th class="text-center text-subtitle-1" style="min-width: 80px;">YoY%</th>
                                            <th v-if="showQoQ" class="text-center text-subtitle-1"
                                                style="min-width: 80px;">QoQ%</th>
                                            <th class="text-center text-subtitle-1 border-e" style="min-width: 80px;">
                                                YTD%</th>
                                        </template>
                                        <template v-else>
                                            <td :colspan="showQoQ ? 4 : 3"></td>
                                        </template>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="growthRateReportTableData.length > 0 && lastPeriod">
                                        <template v-for="(category, catIndex) in growthRateReportTableData"
                                            :key="category.categoryName">

                                            <template v-for="(metricEntry, rowIndex) in [
                                                { key: 'total_units', name: 'จำนวนหลัง', format: metricRows.find(r => r.key === 'total_units')!.format, data: category.total_units, raw_data: category.total_units_raw },
                                                { key: 'total_value', name: 'มูลค่ารวม (บาท)', format: metricRows.find(r => r.key === 'total_value')!.format, data: category.total_value, raw_data: category.total_value_raw }
                                            ]" :key="`${category.categoryName}-${metricEntry.key}`">

                                                <tr :class="{
                                                    'bg-blue-grey-lighten-5': category.categoryName === 'รวม',
                                                    'border-t': rowIndex === 0,
                                                }">
                                                    <td v-if="rowIndex === 0" :rowspan="2"
                                                        class="text-left font-weight-bold text-subtitle-2 border-e"
                                                        :class="{ 'text-primary': category.categoryName === 'รวม' }">
                                                        {{ category.categoryName }}
                                                    </td>

                                                    <td class="text-left text-caption border-e">
                                                        {{ metricEntry.name }}
                                                    </td>

                                                    <template
                                                        v-for="(period, index) in tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS')"
                                                        :key="`raw-cell-${period.key}`">
                                                        <td class="text-right text-subtitle-2 border-e" :class="{
                                                            'text-primary font-weight-bold': category.categoryName === 'รวม' && metricEntry.key === 'total_value'
                                                        }">
                                                            {{ metricEntry.format(metricEntry.raw_data[period.key] || 0)
                                                            }}
                                                        </td>
                                                    </template>

                                                    <template v-if="lastPeriod">
                                                        <td class="text-right text-subtitle-2"
                                                            :class="{ 'text-success': (metricEntry.data[lastPeriod.key]?.MoM || 0) > 0, 'text-error': (metricEntry.data[lastPeriod.key]?.MoM || 0) < 0 }">
                                                            {{ metricEntry.data[lastPeriod.key]?.MoM != null
                                                                ? metricEntry.data[lastPeriod.key]!.MoM!.toFixed(2) + '%'
                                                                : '-'
                                                            }}
                                                        </td>

                                                        <td class="text-right text-subtitle-2"
                                                            :class="{ 'text-success': (metricEntry.data[lastPeriod.key]?.YoY || 0) > 0, 'text-error': (metricEntry.data[lastPeriod.key]?.YoY || 0) < 0 }">
                                                            {{ metricEntry.data[lastPeriod.key]?.YoY != null
                                                                ? metricEntry.data[lastPeriod.key]!.YoY!.toFixed(2) + '%'
                                                                : '-'
                                                            }}
                                                        </td>

                                                        <td v-if="showQoQ" class="text-right text-subtitle-2"
                                                            :class="{ 'text-success': (metricEntry.data[lastPeriod.key]?.QoQ || 0) > 0, 'text-error': (metricEntry.data[lastPeriod.key]?.QoQ || 0) < 0 }">
                                                            {{ metricEntry.data[lastPeriod.key]?.QoQ != null
                                                                ? metricEntry.data[lastPeriod.key]!.QoQ!.toFixed(2) + '%'
                                                                : '-'
                                                            }}
                                                        </td>

                                                        <td class="text-right text-subtitle-2 border-e"
                                                            :class="{ 'text-success': (metricEntry.data[lastPeriod.key]?.YTD || 0) > 0, 'text-error': (metricEntry.data[lastPeriod.key]?.YTD || 0) < 0 }">
                                                            {{ metricEntry.data[lastPeriod.key]?.YTD != null
                                                                ? metricEntry.data[lastPeriod.key]!.YTD!.toFixed(2) + '%'
                                                                : '-'
                                                            }}
                                                        </td>
                                                    </template>
                                                </tr>
                                            </template>
                                        </template>
                                    </template>
                                    <tr v-else>
                                        <td :colspan="2 + tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS').length + (showQoQ ? 4 : 3)"
                                            class="text-center text-subtitle-1 py-4">ไม่พบข้อมูลตามเงื่อนไขที่เลือก
                                            หรือช่วงเวลาที่เลือกไม่ต่อเนื่อง
                                        </td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">กราฟเปรียบเทียบยอดเซ็นสัญญา แยกตามมูลค่าบ้าน (รายเดือน)</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>
                            <apexchart id="chart1" type="line" :options="chartOptions" :series="chartSeries"
                                height="350" v-if="chartSeries.length > 0" />
                            <v-alert v-else type="info" variant="tonal" density="compact" class="mt-4">
                                ไม่พบข้อมูลสำหรับกราฟ (กรุณาเลือกปีที่ต้องการแสดงผล)
                            </v-alert>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="6">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">กราฟสัดส่วนมูลค่ารวม แยกตามมูลค่าบ้าน</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>
                            <apexchart id="polarChartPrice" type="polarArea"
                                :options="{ ...polarChartOptions, labels: polarChartPriceData.labels }"
                                :series="polarChartPriceData.series" height="400"
                                v-if="polarChartPriceData.series.length > 0" />
                            <v-alert v-else type="info" variant="tonal" density="compact" class="mt-4">
                                ไม่พบข้อมูลสำหรับกราฟ (กรุณาเลือกปีที่ต้องการแสดงผล)
                            </v-alert>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="6">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">กราฟสัดส่วนมูลค่ารวม แยกตามภูมิภาค</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>
                            <apexchart id="polarChartRegion" type="polarArea"
                                :options="{ ...polarChartOptions, labels: polarChartRegionData.labels }"
                                :series="polarChartRegionData.series" height="400"
                                v-if="polarChartRegionData.series.length > 0" />
                            <v-alert v-else type="info" variant="tonal" density="compact" class="mt-4">
                                ไม่พบข้อมูลสำหรับกราฟ (กรุณาเลือกปีที่ต้องการแสดงผล)
                            </v-alert>
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">รายงานสถานะการกรอกสัญญาของสมาชิก</h3>
                            <h5 class="card-subtitle">ข้อมูลอ้างอิง: สมาชิกประเภท User ทั้งหมด</h5>
                            <v-row class="mt-4">
                                <v-col cols="12" v-if="memberSubmissionSummary.periodCounts.length > 0">
                                    <v-divider class="my-4"></v-divider>
                                    <v-card-title class="text-center text-subtitle-1 pt-4 pb-0">
                                        สถานะการกรอกสัญญา (จำแนกตามช่วงเวลาที่เลือก)
                                    </v-card-title>
                                    <v-card-text class="pa-2">
                                        <apexchart id="monthlyBarChartMember" type="bar" :key="monthlyBarChartKey"
                                            :options="monthlyBarChartOptions"
                                            :series="monthlySubmissionBarChartData.series" height="350"
                                            v-if="monthlySubmissionBarChartData.series.length > 0 && monthlySubmissionBarChartData.series[0].data.length > 0" />
                                        <v-alert v-else type="info" variant="tonal" density="compact" class="mt-4">
                                            ไม่พบข้อมูลตามช่วงเวลาที่เลือก
                                        </v-alert>
                                    </v-card-text>
                                </v-col>

                                <v-col cols="12" md="6">
                                    <div>
                                        <v-card-title
                                            class="text-center text-subtitle-1 pt-4 pb-0">สถานะการกรอกสัญญารวม</v-card-title>
                                        <v-card-text class="pa-2">
                                            <apexchart id="donutChartMember" type="donut" :options="donutChartOptions"
                                                :series="memberSubmissionSummary.donutData" height="350"
                                                v-if="memberSubmissionSummary.donutData.length > 0 && memberSubmissionSummary.donutData.some(d => d > 0)" />
                                            <v-alert v-else type="info" variant="tonal" density="compact" class="mt-4">
                                                ไม่พบข้อมูลสมาชิกหรือข้อมูลการกรอกสัญญา
                                            </v-alert>
                                        </v-card-text>
                                    </div>
                                </v-col>

                                <v-col cols="12" md="6">
                                    <v-table density="compact" class="mt-4 border">
                                        <thead>
                                            <tr>
                                                <th colspan="2" class="text-center text-subtitle-1">สรุปจำนวนสมาชิก</th>
                                            </tr>
                                        </thead>
                                        <tbody>


                                            <!-- <tr class="bg-blue-grey-lighten-5">
                                                <td class="font-weight-bold">สมาชิกทั้งหมด (รวมผู้ดูแล)</td>
                                                <td class="text-right font-weight-bold">{{
                                                    memberSubmissionSummary.totalUsersIncludingAdmin }} คน</td>
                                            </tr> -->
                                            <tr>
                                                <td class="font-weight-bold">สมาชิกทั้งหมด</td>
                                                <td class="text-right font-weight-bold">{{
                                                    memberSubmissionSummary.totalUsers }} คน</td>
                                            </tr>


                                            <template v-if="memberSubmissionSummary.periodCounts.length > 0">
                                                <tr class="bg-blue-grey-lighten-5">

                                                </tr>
                                                <tr v-for="period in memberSubmissionSummary.periodCounts"
                                                    :key="period.key">
                                                    <td>{{ period.label }}</td>
                                                    <td class="text-right">{{ period.count }} คน</td>
                                                </tr>
                                            </template>
                                            <tr>
                                                <td>{{ memberSubmissionSummary.submittedLabel }}</td>
                                                <td class="text-right">{{ memberSubmissionSummary.submittedTotal }} คน
                                                </td>
                                            </tr>
                                            <tr class="bg-red-lighten-5">
                                                <td class="font-weight-bold text-error">{{
                                                    memberSubmissionSummary.notSubmittedLabel }}
                                                </td>
                                                <td class="text-right font-weight-bold text-error">{{
                                                    memberSubmissionSummary.notSubmittedTotal }} คน</td>
                                            </tr>
                                        </tbody>
                                    </v-table>
                                </v-col>


                                <!-- <v-col cols="12" md="12">

                                    <v-card-title
                                        class="text-center text-subtitle-1 pt-4 pb-0">จำนวนสัญญาที่กรอกต่อรายสมาชิก
                                        (ปีที่เลือก)</v-card-title>
                                    <v-card-text class="pa-2">
                                        <apexchart id="barChartMember" type="bar" :key="barChartKey"
                                            :options="barChartOptions" :series="memberListChartData.series"
                                            :height="barChartHeight" />
                                    </v-card-text>

                                </v-col> -->
                            </v-row>
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12">
                    <v-card>
                        <v-card-text>

                            <h3 class="card-title mb-1">ตารางสถานะการกรอกสัญญาต่อเดือน (แยกตามรายสมาชิก)</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>
                            <v-divider class="my-4"></v-divider>

                            <v-col cols="12">
                                <v-card-title class="text-center text-subtitle-1 pt-4 pb-0">
                                    สถานะการกรอกสัญญา (จำแนกตามประเภทสมาชิก)
                                </v-card-title>
                                <v-card-text class="pa-2">
                                    <apexchart id="memberTypeBarChart" type="bar" :key="memberTypeBarChartKey"
                                        :options="memberTypeBarChartOptions"
                                        :series="memberTypeSubmissionChartData.series" height="350" class="mt-4"
                                        v-if="memberTypeSubmissionChartData.series.length > 0" />

                                    <v-alert v-else type="info" variant="tonal" density="compact" class="mt-4">
                                        ไม่พบข้อมูลสำหรับกราฟนี้ (กรุณาเลือกช่วงเวลาเพื่อแสดงผล)
                                    </v-alert>
                                </v-card-text>
                            </v-col>


                            <v-table density="compact" class="mt-4  border">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 200px;">
                                            รายชื่อสมาชิก</th>

                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 100px;">
                                            ประเภท </th>

                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 100px;">
                                            ยอดรวม </th>

                                        <th :colspan="tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS').length"
                                            class="text-center text-h6 border-b-sm">
                                            <span v-if="tablePeriods.length > 0">{{ chartSubtitle }}</span>
                                            <span v-else>ไม่พบช่วงเวลาที่เลือก</span>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th v-for="period in tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS')"
                                            :key="period.key" class="text-center text-subtitle-1"
                                            style="min-width: 80px;">
                                            {{ period.label }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="memberMonthlySubmissionTableData.length > 0">
                                        <tr v-for="member in memberMonthlySubmissionTableData" :key="member.name">
                                            <td class="text-left font-weight-bold text-caption border-e">{{ member.name
                                            }}</td>

                                            <td class="text-left text-caption border-e">
                                                {{ member.member_type }} </td>
                                            <td class="text-center font-weight-bold text-subtitle-2 border-e">
                                                {{ member.total_submitted_in_period.toLocaleString('th-TH') }}
                                            </td>
                                            <td v-for="period in tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS')"
                                                :key="period.key" class="text-center text-subtitle-2"
                                                :class="{ 'text-success font-weight-bold': member.submissions[period.key] === 'X', 'text-error': member.submissions[period.key] === '-' }">
                                                {{ member.submissions[period.key] }}
                                            </td>
                                        </tr>
                                    </template>
                                    <tr v-else>
                                        <td :colspan="3 + tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS').length"
                                            class="text-center text-subtitle-1 py-4">
                                            ไม่พบข้อมูลสมาชิกที่แสดงผลตามเงื่อนไข
                                        </td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </v-card-text>
                    </v-card>
                </v-col>


                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">ตารางสรุปยอดเซ็นสัญญา แยกตามมูลค่าบ้าน</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>

                            <v-table density="compact" class="mt-4 border">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">
                                            มูลค่าบ้าน
                                        </th>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">
                                            รายละเอียด</th>
                                        <th :colspan="tablePeriods.length"
                                            class="text-center text-h6 border-b-sm border-e">
                                            <span v-if="tablePeriods.length > 0">{{ chartSubtitle }}</span>
                                            <span v-else>ไม่พบช่วงเวลาที่เลือก</span>
                                        </th>
                                        <th v-if="lastPeriod && lastPeriod.monthIndex" :colspan="2"
                                            class="text-center text-h6 border-b-sm bg-blue-grey-lighten-5">
                                            อัตราเติบโต (ณ {{ lastPeriod.label }})
                                        </th>
                                    </tr>
                                    <tr>
                                        <th v-for="(period, index) in tablePeriods" :key="period.key"
                                            class="text-right text-subtitle-1 border-e" :class="{
                                                'text-primary': period.key === 'TOTAL_PERIODS'
                                            }" style="min-width: 120px;">
                                            {{ period.label }}
                                        </th>
                                        <th v-if="lastPeriod && lastPeriod.monthIndex"
                                            class="text-center text-subtitle-1 border-e" style="min-width: 80px;">MoM%
                                        </th>
                                        <th v-if="lastPeriod && lastPeriod.monthIndex"
                                            class="text-center text-subtitle-1" style="min-width: 80px;">YTD%
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="monthlyReportTableData.length > 0">
                                        <template v-for="(category, catIndex) in monthlyReportTableData"
                                            :key="category.categoryName">
                                            <tr v-for="(row, rowIndex) in category.rows"
                                                :key="`${category.categoryName}-${row.metricKey}`" :class="{
                                                    'bg-blue-grey-lighten-5': category.categoryName === 'รวม',
                                                    'border-t': rowIndex === 0,
                                                }">
                                                <td v-if="rowIndex === 0" :rowspan="category.rows.length"
                                                    class="text-left font-weight-bold text-subtitle-2 border-e"
                                                    :class="{ 'text-primary': category.categoryName === 'รวม' }">
                                                    {{ category.categoryName }}
                                                </td>

                                                <td class="text-left text-caption border-e">{{ row.metricName }}</td>

                                                <td v-for="(period, index) in tablePeriods" :key="period.key"
                                                    class="text-right text-subtitle-2 border-e" :class="{
                                                        'text-primary font-weight-bold': category.categoryName === 'รวม' && row.metricKey === 'total_value',
                                                    }">
                                                    {{ row.format(row.data[period.key] || 0) }}
                                                </td>

                                                <td v-if="lastPeriod && lastPeriod.monthIndex"
                                                    class="text-right text-subtitle-2 border-e"
                                                    :class="{ 'text-success': (row.growth.mom || 0) > 0, 'text-error': (row.growth.mom || 0) < 0 }">
                                                    {{ row.growth.mom != null ? row.growth.mom.toFixed(2) + '%' : '-' }}
                                                </td>
                                                <td v-if="lastPeriod && lastPeriod.monthIndex"
                                                    class="text-right text-subtitle-2"
                                                    :class="{ 'text-success': (row.growth.ytd || 0) > 0, 'text-error': (row.growth.ytd || 0) < 0 }">
                                                    {{ row.growth.ytd != null ? row.growth.ytd.toFixed(2) + '%' : '-' }}
                                                </td>
                                            </tr>
                                        </template>
                                    </template>
                                    <tr v-else>
                                        <td :colspan="tablePeriods.length + 4" class="text-center text-subtitle-1 py-4">
                                            ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">ตารางสรุปยอดเซ็นสัญญา แยกตามภูมิภาค</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>

                            <v-table density="compact" class="mt-4 border">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">ภูมิภาค
                                        </th>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">
                                            รายละเอียด</th>
                                        <th :colspan="tablePeriods.length"
                                            class="text-center text-h6 border-b-sm border-e">
                                            <span v-if="tablePeriods.length > 0">{{ chartSubtitle }}</span>
                                            <span v-else>ไม่พบช่วงเวลาที่เลือก</span>
                                        </th>
                                        <th v-if="lastPeriod && lastPeriod.monthIndex" :colspan="2"
                                            class="text-center text-h6 border-b-sm bg-blue-grey-lighten-5">
                                            อัตราเติบโต (ณ {{ lastPeriod.label }})
                                        </th>
                                    </tr>
                                    <tr>
                                        <th v-for="(period, index) in tablePeriods" :key="period.key"
                                            class="text-right text-subtitle-1 border-e" :class="{
                                                'text-primary': period.key === 'TOTAL_PERIODS'
                                            }" style="min-width: 120px;">
                                            {{ period.label }}
                                        </th>
                                        <th v-if="lastPeriod && lastPeriod.monthIndex"
                                            class="text-center text-subtitle-1 border-e" style="min-width: 80px;">MoM%
                                        </th>
                                        <th v-if="lastPeriod && lastPeriod.monthIndex"
                                            class="text-center text-subtitle-1" style="min-width: 80px;">YTD%
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="regionReportTableData.length > 0">
                                        <template v-for="(region, regIndex) in regionReportTableData"
                                            :key="region.categoryName">
                                            <tr v-for="(row, rowIndex) in region.rows"
                                                :key="`${region.categoryName}-${row.metricKey}`" :class="{
                                                    'bg-blue-grey-lighten-5': region.categoryName === 'รวมทั่วประเทศ',
                                                    'border-t': rowIndex === 0,
                                                }">
                                                <td v-if="rowIndex === 0" :rowspan="region.rows.length"
                                                    class="text-left font-weight-bold text-subtitle-2 border-e"
                                                    :class="{ 'text-primary': region.categoryName === 'รวมทั่วประเทศ' }">
                                                    {{ region.categoryName }}
                                                </td>

                                                <td class="text-left text-caption border-e">{{ row.metricName }}</td>

                                                <td v-for="(period, index) in tablePeriods" :key="period.key"
                                                    class="text-right text-subtitle-2 border-e" :class="{
                                                        'text-primary font-weight-bold': region.categoryName === 'รวมทั่วประเทศ' && row.metricKey === 'total_value',
                                                    }">
                                                    {{ row.format(row.data[period.key] || 0) }}
                                                </td>

                                                <td v-if="lastPeriod && lastPeriod.monthIndex"
                                                    class="text-right text-subtitle-2 border-e"
                                                    :class="{ 'text-success': (row.growth.mom || 0) > 0, 'text-error': (row.growth.mom || 0) < 0 }">
                                                    {{ row.growth.mom != null ? row.growth.mom.toFixed(2) + '%' : '-' }}
                                                </td>
                                                <td v-if="lastPeriod && lastPeriod.monthIndex"
                                                    class="text-right text-subtitle-2"
                                                    :class="{ 'text-success': (row.growth.ytd || 0) > 0, 'text-error': (row.growth.ytd || 0) < 0 }">
                                                    {{ row.growth.ytd != null ? row.growth.ytd.toFixed(2) + '%' : '-' }}
                                                </td>
                                            </tr>
                                        </template>
                                    </template>
                                    <tr v-else>
                                        <td :colspan="tablePeriods.length + 4" class="text-center text-subtitle-1 py-4">
                                            ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">ตารางสรุปยอดเซ็นสัญญา แยกตามภูมิภาค และมูลค่าบ้าน</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>

                            <v-table density="compact" class="mt-4 border">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">ภูมิภาค
                                        </th>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">
                                            ช่วงมูลค่าบ้าน</th>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">
                                            รายละเอียด</th>
                                        <th :colspan="tablePeriods.length"
                                            class="text-center text-h6 border-b-sm border-e">
                                            <span v-if="tablePeriods.length > 0">{{ chartSubtitle }}</span>
                                            <span v-else>ไม่พบช่วงเวลาที่เลือก</span>
                                        </th>
                                        <th v-if="lastPeriod && lastPeriod.monthIndex" :colspan="2"
                                            class="text-center text-h6 border-b-sm bg-blue-grey-lighten-5">
                                            อัตราเติบโต (ณ {{ lastPeriod.label }})
                                        </th>
                                    </tr>
                                    <tr>
                                        <th v-for="(period, index) in tablePeriods" :key="period.key"
                                            class="text-right text-subtitle-1 border-e" :class="{
                                                'text-primary': period.key === 'TOTAL_PERIODS'
                                            }" style="min-width: 120px;">
                                            {{ period.label }}
                                        </th>
                                        <th v-if="lastPeriod && lastPeriod.monthIndex"
                                            class="text-center text-subtitle-1 border-e" style="min-width: 80px;">MoM%
                                        </th>
                                        <th v-if="lastPeriod && lastPeriod.monthIndex"
                                            class="text-center text-subtitle-1" style="min-width: 80px;">YTD%
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="regionAndCategoryReportTableData.length > 0">
                                        <template v-for="(regionGroup, regIndex) in regionAndCategoryReportTableData"
                                            :key="regionGroup.regionName">
                                            <template v-for="(category, catIndex) in regionGroup.categories"
                                                :key="`${regionGroup.regionName}-${category.categoryName}`">
                                                <tr v-for="(row, rowIndex) in category.rows"
                                                    :key="`${regionGroup.regionName}-${category.categoryName}-${row.metricKey}`"
                                                    :class="{
                                                        'bg-blue-grey-lighten-5': regionGroup.regionName === 'รวมทั่วประเทศ' || category.categoryName === 'รวม',
                                                        'border-t': rowIndex === 0 && catIndex === 0,
                                                        'border-t-sm': rowIndex === 0 && category.categoryName === 'รวม',
                                                    }">
                                                    <td v-if="rowIndex === 0 && catIndex === 0"
                                                        :rowspan="regionGroup.categories.length * category.rows.length"
                                                        class="text-left font-weight-bold text-subtitle-2 border-e"
                                                        :class="{ 'text-primary': regionGroup.regionName === 'รวมทั่วประเทศ' }">
                                                        {{ regionGroup.regionName }}
                                                    </td>

                                                    <td v-if="rowIndex === 0" :rowspan="category.rows.length"
                                                        class="text-left font-weight-bold text-caption border-e"
                                                        :class="{ 'text-primary': category.categoryName === 'รวม' }">
                                                        {{ category.categoryName }}
                                                    </td>

                                                    <td class="text-left text-caption border-e">{{ row.metricName }}
                                                    </td>

                                                    <td v-for="(period, index) in tablePeriods" :key="period.key"
                                                        class="text-right text-subtitle-2 border-e" :class="{
                                                            'text-primary font-weight-bold': (regionGroup.regionName === 'รวมทั่วประเทศ' || category.categoryName === 'รวม') && row.metricKey === 'total_value',
                                                        }">
                                                        {{ row.format(row.data[period.key] || 0) }}
                                                    </td>

                                                    <td v-if="lastPeriod && lastPeriod.monthIndex"
                                                        class="text-right text-subtitle-2 border-e"
                                                        :class="{ 'text-success': (row.growth.mom || 0) > 0, 'text-error': (row.growth.mom || 0) < 0 }">
                                                        {{ row.growth.mom != null ? row.growth.mom.toFixed(2) + '%' :
                                                            '-' }}
                                                    </td>
                                                    <td v-if="lastPeriod && lastPeriod.monthIndex"
                                                        class="text-right text-subtitle-2"
                                                        :class="{ 'text-success': (row.growth.ytd || 0) > 0, 'text-error': (row.growth.ytd || 0) < 0 }">
                                                        {{ row.growth.ytd != null ? row.growth.ytd.toFixed(2) + '%' :
                                                            '-' }}
                                                    </td>
                                                </tr>
                                            </template>
                                        </template>
                                    </template>
                                    <tr v-else>
                                        <td :colspan="tablePeriods.length + 5" class="text-center text-subtitle-1 py-4">
                                            ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </v-app>
</template>
