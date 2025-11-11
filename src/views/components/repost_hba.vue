<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useDate } from 'vuetify/lib/framework.mjs';

import ExcelJS from 'exceljs';
import type { BorderStyle, Cell, Style, Alignment, Font, Fill } from 'exceljs';

const date = useDate();



const today = new Date();
const currentGregorianYear = today.getFullYear(); // 1. ดึงปี ค.ศ. (เช่น 2025)
const currentBuddhistYearNum = currentGregorianYear + 543; // 2. บวก 543 (เช่น 2568)
const currentBuddhistYearStr = currentBuddhistYearNum.toString(); // 3. แปลงเป็น "2568"
const previousBuddhistYearStr = (currentBuddhistYearNum - 1).toString(); // 4. "2567"

const tab = ref('monthly');

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

                return `${yearText} - ${Q.name} (${Q.names.join(' - ')})`;
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


                let currentYTDValue: number;
                let prevYTDValue: number;

                if (metric.key === 'average_price_per_sqm') {
                    const currentYTD_Value = getAggregatedValueForMetric(currentYear, 1, currentMonth, categoryName, 'total_value');
                    const currentYTD_Area = getAggregatedValueForMetric(currentYear, 1, currentMonth, categoryName, 'total_area');
                    currentYTDValue = (currentYTD_Area > 0) ? (currentYTD_Value / currentYTD_Area) : 0;

                    const prevYTD_Value = getAggregatedValueForMetric(prevYear, 1, currentMonth, categoryName, 'total_value');
                    const prevYTD_Area = getAggregatedValueForMetric(prevYear, 1, currentMonth, categoryName, 'total_area');
                    prevYTDValue = (prevYTD_Area > 0) ? (prevYTD_Value / prevYTD_Area) : 0;
                } else {
                    currentYTDValue = getAggregatedValueForMetric(currentYear, 1, currentMonth, categoryName, metric.key as keyof Metrics);
                    prevYTDValue = getAggregatedValueForMetric(prevYear, 1, currentMonth, categoryName, metric.key as keyof Metrics);
                }


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

////////////////////////////////////////////////////////

const COMMON_FONT_NAME = 'Angsana New';
const TITLE_ROWS = 3; // (Title + Subtitle + Blank)

// NOTE: ใช้ Type Literal ใน fill.type: 'pattern' เพื่อแก้ Type Error
const FONT_DEFAULT: Partial<Font> = { name: COMMON_FONT_NAME, size: 11, color: { argb: 'FF000000' }, bold: false };
const FONT_TOTAL_PRIMARY: Partial<Font> = { name: COMMON_FONT_NAME, size: 12, bold: true, color: { argb: 'FF3F51B5' } };

const STYLE_BORDER: Partial<ExcelJS.Borders> = {
    top: { style: 'thin' as BorderStyle }, bottom: { style: 'thin' as BorderStyle },
    left: { style: 'thin' as BorderStyle }, right: { style: 'thin' as BorderStyle }
};

const STYLE_ALIGNMENT_CENTER: Partial<Alignment> = { vertical: 'middle', horizontal: 'center' };
const STYLE_ALIGNMENT_RIGHT: Partial<Alignment> = { vertical: 'middle', horizontal: 'right' };
const STYLE_ALIGNMENT_LEFT: Partial<Alignment> = { vertical: 'middle', horizontal: 'left' };

const STYLE_HEADER: Partial<Style> = {
    font: { name: COMMON_FONT_NAME, size: 12, bold: true },
    fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFA3C9E2' } } as Fill,
    border: STYLE_BORDER,
    alignment: STYLE_ALIGNMENT_CENTER,
};

const STYLE_DEFAULT: Partial<Style> = {
    font: FONT_DEFAULT,
    fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFFFFF' } } as Fill,
    border: STYLE_BORDER,
    alignment: STYLE_ALIGNMENT_RIGHT,
    numFmt: 'General'
};

const STYLE_GROUP_LEFT: Partial<Style> = {
    ...STYLE_DEFAULT,
    font: { ...FONT_DEFAULT, bold: true },
    alignment: STYLE_ALIGNMENT_LEFT,
};

const STYLE_TOTAL: Partial<Style> = {
    ...STYLE_DEFAULT,
    font: { ...FONT_DEFAULT, bold: true },
    fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD9E1F2' } } as Fill,
};

const STYLE_PRIMARY_TOTAL: Partial<Style> = {
    ...STYLE_TOTAL,
    font: FONT_TOTAL_PRIMARY,
    fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFB4C6E7' } } as Fill,
};

const STYLE_GROWTH_GREEN: Partial<Style> = {
    ...STYLE_DEFAULT,
    font: { ...FONT_DEFAULT, color: { argb: 'FF00B050' }, bold: true },
};

const STYLE_GROWTH_RED: Partial<Style> = {
    ...STYLE_DEFAULT,
    font: { ...FONT_DEFAULT, color: { argb: 'FFFF0000' }, bold: true },
};


// --------------------------------------------------------------------
// 2. HELPER FUNCTION DEFINITIONS (ต้องอยู่ก่อน exportToExcel)
// --------------------------------------------------------------------

/**
 * Sheets 4, 5, 6: สรุปตามมูลค่าบ้าน, ภูมิภาค, ภูมิภาคและมูลค่า
 */
const populateVerticalTableExport = (
    worksheet: ExcelJS.Worksheet,
    tableData: any,
    periods: any,
    isRegionCategory: boolean = false,
    isRegionAndCategory: boolean = false,
    startRow: number
): number => {
    let currentRow = startRow;
    const periodsToDisplay = periods.filter((p: any) => p.key !== 'TOTAL_PERIODS');
    const lp = periodsToDisplay.length > 0 ? periodsToDisplay[periodsToDisplay.length - 1] : null;
    const hasGrowth = lp && lp.monthIndex !== undefined;

    // --- 1. Header Generation ---
    let finalHeaders: any[][] = [];
    if (isRegionAndCategory) {
        finalHeaders.push(['ภูมิภาค', 'ช่วงมูลค่าบ้าน', 'รายละเอียด', ...periods.map((p: any) => p.label)]);
        finalHeaders.push(['', '', '', ...periods.map((p: any) => p.label)]);
    } else if (!isRegionCategory) {
        finalHeaders.push(['มูลค่าบ้าน', 'รายละเอียด', ...periods.map((p: any) => p.label)]);
        finalHeaders.push(['', '', ...periods.map((p: any) => p.label)]);
    } else {
        finalHeaders.push(['ภูมิภาค', 'รายละเอียด', ...periods.map((p: any) => p.label)]);
        finalHeaders.push(['', '', ...periods.map((p: any) => p.label)]);
    }
    if (hasGrowth) {
        finalHeaders[0].push('MoM%', 'YTD%');
        finalHeaders[1].push('อัตราเติบโต', 'อัตราเติบโต');
    }
    const headerRowsCount = finalHeaders.length;
    const dataStartRow = startRow + headerRowsCount;

    // Write Headers and Apply Style
    finalHeaders.forEach((headerRow, rIndex) => {
        const row = worksheet.getRow(startRow + rIndex);
        row.values = [''].concat(headerRow);
        row.height = 20;
        row.eachCell((cell, colIndex) => {
            cell.style = STYLE_HEADER as ExcelJS.Style;
        });
    });

    // --- Apply Merge for Headers ---
    let startColIndex = 1;
    worksheet.mergeCells(startRow, 1, startRow + headerRowsCount - 1, 1);
    startColIndex++;

    if (isRegionAndCategory) {
        worksheet.mergeCells(startRow, 2, startRow + headerRowsCount - 1, 2);
        startColIndex++;
    }
    worksheet.mergeCells(startRow, startColIndex, startRow + headerRowsCount - 1, startColIndex);

    if (hasGrowth) {
        const momYtdStartCol = finalHeaders[0].length;
        worksheet.mergeCells(startRow + 1, momYtdStartCol, startRow + 1, momYtdStartCol + 1);
    }

    currentRow = dataStartRow;

    // --- 2. Data Row Generation and Merges ---
    (tableData as any[]).forEach((group: any) => {
        const primaryGroupKey = isRegionAndCategory ? group.regionName : group.categoryName;
        const secondaryGroups = isRegionAndCategory ? group.categories : [group];

        secondaryGroups.forEach((category: any, categoryIndex: number) => {
            const secondaryGroupKey = category.categoryName;
            const groupRowCount = category.rows.length;

            category.rows.forEach((row: any, rowIndex: number) => {
                const isPrimaryTotal = primaryGroupKey === 'รวมทั่วประเทศ' || primaryGroupKey === 'รวม';
                const isSecondaryTotal = secondaryGroupKey === 'รวม';
                const metricName = row.metricName;
                const isTotalMetric = metricName === 'มูลค่ารวม (บาท)';

                const baseRowStyle = isPrimaryTotal || isSecondaryTotal ? STYLE_TOTAL : STYLE_DEFAULT;
                let rowData: any[] = [];

                // Col 0: Primary Group (Row Data)
                rowData.push(rowIndex === 0 && (!isRegionAndCategory || categoryIndex === 0) ? primaryGroupKey : '');

                // Apply Merge for Primary Group (Vertical)
                if (rowIndex === 0 && (!isRegionAndCategory || categoryIndex === 0)) {
                    const mergeSpan = isRegionAndCategory ? (group.categories.length * groupRowCount) : groupRowCount;
                    if (mergeSpan > 1) {
                        worksheet.mergeCells(currentRow, 1, currentRow + mergeSpan - 1, 1);
                    }
                }

                // Col 1: Secondary Group (Row Data)
                if (isRegionAndCategory) {
                    rowData.push(rowIndex === 0 ? secondaryGroupKey : '');
                    // Apply Merge for Secondary Group (Vertical)
                    if (rowIndex === 0) {
                        const mergeSpan = groupRowCount;
                        if (mergeSpan > 1) {
                            worksheet.mergeCells(currentRow, 2, currentRow + mergeSpan - 1, 2);
                        }
                    }
                }

                // Metric Name Column (Col 1 or 2)
                rowData.push(metricName);

                // Period Data Columns and Growth Columns
                periods.forEach((p: any) => {
                    const value = row.data[p.key] || 0;
                    rowData.push(p.key === 'TOTAL_PERIODS' ? row.format(value).replace(/,/g, '') : value);
                });

                if (hasGrowth) {
                    const momRate = row.growth.mom;
                    const ytdRate = row.growth.ytd;
                    rowData.push(momRate !== null ? momRate / 100 : '-');
                    rowData.push(ytdRate !== null ? ytdRate / 100 : '-');
                }

                // Write Data Row
                const newRow = worksheet.getRow(currentRow);
                newRow.values = [''].concat(rowData);
                newRow.height = 18;

                // Apply Styles
                let colIdxOffset = 1;

                // Col 1: Primary Group Style
                const cell0 = newRow.getCell(1);
                cell0.style = isPrimaryTotal ? STYLE_PRIMARY_TOTAL as ExcelJS.Style : STYLE_GROUP_LEFT as ExcelJS.Style;
                colIdxOffset++;

                // Col 2: Secondary Group Style (if applicable)
                if (isRegionAndCategory) {
                    const cell1 = newRow.getCell(2);
                    cell1.style = isSecondaryTotal ? STYLE_PRIMARY_TOTAL as ExcelJS.Style : STYLE_GROUP_LEFT as ExcelJS.Style;
                    colIdxOffset++;
                }

                // Col (1 or 2): Metric Name Style
                const cellMetric = newRow.getCell(colIdxOffset);
                cellMetric.style = {
                    ...baseRowStyle,
                    alignment: STYLE_ALIGNMENT_LEFT,
                    font: { ...baseRowStyle.font, bold: isTotalMetric || baseRowStyle.font?.bold }
                } as ExcelJS.Style;
                colIdxOffset++;

                // Period Data Columns (Numbers)
                for (let c = colIdxOffset; c < newRow.values.length - (hasGrowth ? 2 : 0); c++) {
                    const cell = newRow.getCell(c);
                    let style: Partial<Style> = {
                        ...baseRowStyle,
                        numFmt: (metricName.includes('(บาท)')) ? '#,##0.00' : '#,##0',
                    };

                    if (isPrimaryTotal && isTotalMetric) {
                        style = {
                            ...style,
                            font: FONT_TOTAL_PRIMARY,
                            fill: STYLE_PRIMARY_TOTAL.fill,
                            numFmt: '#,##0.00'
                        };
                    } else if (isPrimaryTotal || isSecondaryTotal) {
                        style = {
                            ...style,
                            font: { ...baseRowStyle.font, bold: true },
                            fill: STYLE_TOTAL.fill
                        };
                    }
                    cell.style = style as ExcelJS.Style;
                }

                // Growth Columns (MoM, YTD)
                if (hasGrowth) {
                    const momCol = newRow.values.length - 1;
                    const ytdCol = newRow.values.length;

                    // MoM Cell
                    const momCell = newRow.getCell(momCol);
                    if (momCell.value !== '-') {
                        momCell.style = momCell.value && (momCell.value as number) > 0 ? STYLE_GROWTH_GREEN : STYLE_GROWTH_RED;
                    } else { momCell.style = baseRowStyle as ExcelJS.Style; }
                    momCell.numFmt = '0.00%';

                    // YTD Cell
                    const ytdCell = newRow.getCell(ytdCol);
                    if (ytdCell.value !== '-') {
                        ytdCell.style = ytdCell.value && (ytdCell.value as number) > 0 ? STYLE_GROWTH_GREEN : STYLE_GROWTH_RED;
                    } else { ytdCell.style = baseRowStyle as ExcelJS.Style; }
                    ytdCell.numFmt = '0.00%';
                }

                currentRow++;
            });
        });
    });

    return currentRow - 1;
};


/**
 * Sheet 1: อัตราเติบโต (สรุป)
 */
const populateGrowthRateReportExport = (
    worksheet: ExcelJS.Worksheet,
    data: any,
    periods: any,
    showQoQValue: boolean,
    startRow: number
): number => {
    let currentRow = startRow;
    const periodsToDisplay = periods.filter((p: any) => p.key !== 'TOTAL_PERIODS');

    if (!data || periodsToDisplay.length === 0) return currentRow;

    const metricsToInclude: any[] = [
        { key: 'total_units', name: 'จำนวนหลัง' },
        { key: 'total_value', name: 'มูลค่ารวม (บาท)' },
    ];

    const metricGroups: any[] = [...valueCategories, 'รวม']; // Assuming valueCategories is defined
    const headersCount = 1;

    // --- Data Generation ---
    const header = ['ช่วงมูลค่าบ้าน', 'ตัวชี้วัด', ...periodsToDisplay.map((p: any) => p.label)];
    const finalData: any[][] = [];
    const merges: { s: { r: number, c: number }, e: { r: number, c: number } }[] = [];
    let currentDataRow = 0;

    metricGroups.forEach((categoryName: any) => {
        const categoryData = data.find((c: any) => c.categoryName === categoryName);
        if (!categoryData) return;

        let rowCount = 0;
        const totalRowsPerGroup = metricsToInclude.length * (showQoQValue ? 5 : 4);

        metricsToInclude.forEach((metric) => {
            const metricKey = metric.key;

            type MetricOrGrowthKey = any; // Simplify type checking in this context
            const growthKeys: { key: MetricOrGrowthKey, label: string }[] = [
                { key: 'Raw', label: metric.name + ' (ค่าดิบ)' },
                { key: 'MoM', label: 'MoM%' },
                { key: 'YoY', label: 'YoY%' },
                ...(showQoQValue ? [{ key: 'QoQ', label: 'QoQ%' }] : []),
                { key: 'YTD', label: 'YTD%' },
            ];

            growthKeys.forEach((g) => {
                const row: any[] = [];

                if (rowCount % totalRowsPerGroup === 0) {
                    merges.push({ s: { r: currentDataRow + currentRow, c: 1 }, e: { r: currentDataRow + currentRow + totalRowsPerGroup - 1, c: 1 } });
                    row.push(categoryName);
                } else {
                    row.push('');
                }

                row.push(g.label);

                periodsToDisplay.forEach((p: any) => {
                    if (g.key === 'Raw') {
                        const value = categoryData[`${metricKey}_raw`][p.key] || 0;
                        row.push(value);
                    } else {
                        const rate = categoryData[metricKey][p.key][g.key];
                        row.push(rate !== null ? rate / 100 : '-');
                    }
                });

                finalData.push(row);
                currentDataRow++;
                rowCount++;
            });
        });
    });

    // --- ExcelJS Application ---
    const groupStyle: Partial<Style> = { ...STYLE_DEFAULT, fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFEBF1DE' } } as Fill, alignment: STYLE_ALIGNMENT_LEFT, font: { ...STYLE_DEFAULT.font, bold: true } };
    const totalStyle: Partial<Style> = { ...STYLE_DEFAULT, fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD9E1F2' } } as Fill, font: { ...STYLE_DEFAULT.font, size: 12, bold: true } };

    // Write Header
    const headerRowSheet = worksheet.getRow(currentRow);
    headerRowSheet.values = [''].concat(header);
    headerRowSheet.height = 20;
    headerRowSheet.eachCell((cell) => { cell.style = STYLE_HEADER as ExcelJS.Style; });
    currentRow++;

    // Write Data and Apply Styles
    finalData.forEach((row: any, rowIndex: number) => {
        const excelRow = currentRow + rowIndex;
        const newRow = worksheet.getRow(excelRow);
        newRow.values = [''].concat(row);
        newRow.height = 18;

        const categoryName = row[0];
        const indicatorName = row[1];
        const isTotalCategory = categoryName === 'รวม';

        // Col 1: Category Group (Merged)
        const cell0 = newRow.getCell(1);
        cell0.style = isTotalCategory ? totalStyle as ExcelJS.Style : groupStyle as ExcelJS.Style;

        // Col 2: Indicator Name
        const cell1 = newRow.getCell(2);
        let indicatorStyle = isTotalCategory ? totalStyle : STYLE_DEFAULT;
        cell1.style = {
            ...indicatorStyle,
            alignment: STYLE_ALIGNMENT_LEFT,
            font: { ...indicatorStyle.font, bold: isTotalCategory || indicatorStyle.font?.bold }
        } as ExcelJS.Style;

        // Cols 3+: Period Values (Growth Rates)
        for (let c = 3; c <= newRow.values.length; c++) {
            const cell = newRow.getCell(c);
            const value = row[c - 2];
            let style: Partial<Style> = isTotalCategory ? totalStyle : STYLE_DEFAULT;
            const isRawValue = indicatorName.includes('ค่าดิบ');

            if (isRawValue) {
                style = { ...style, numFmt: (indicatorName.includes('(บาท)')) ? '#,##0.00' : '#,##0' };
            } else if (indicatorName.includes('%')) {
                style.numFmt = '0.00%';
                if (value !== '-' && typeof value === 'number') {
                    if (value > 0) { style = STYLE_GROWTH_GREEN; style.numFmt = '0.00%'; }
                    else if (value < 0) { style = STYLE_GROWTH_RED; style.numFmt = '0.00%'; }
                    else if (isTotalCategory) {
                        style = { ...style, font: { ...style.font, bold: true } };
                    }
                }
            }
            cell.style = style as ExcelJS.Style;
        }
    });
    currentRow += finalData.length;

    // Apply Merges
    merges.forEach(m => {
        worksheet.mergeCells(m.s.r, m.s.c, m.e.r, m.e.c);
    });

    return currentRow - 1;
};


/**
 * Sheet 2: สรุปสถานะสมาชิก
 */
const populateMemberSummaryReportExport = (
    worksheet: ExcelJS.Worksheet,
    summary: any,
    startRow: number
): number => {
    let currentRow = startRow;
    const periods = summary.periodCounts;

    const header = ['รายละเอียด', 'จำนวนสมาชิก (คน)'];
    // FIX: ใช้ null แทน '' ในแถวเว้นวรรคเพื่อป้องกัน Invalid Address A
    const data: any[][] = [
        ['สมาชิกทั้งหมด (ประเภท User)', summary.totalUsers.toLocaleString('th-TH')],
        [null, null],
        ['จำนวนสมาชิกที่กรอกในแต่ละช่วงเวลา:', null],
        ...periods.map((p: any) => [`> ${p.label}`, p.count.toLocaleString('th-TH')]),
        [null, null],
        [summary.submittedLabel, summary.submittedTotal.toLocaleString('th-TH')],
        [summary.notSubmittedLabel, summary.notSubmittedTotal.toLocaleString('th-TH')]
    ];

    // --- Style Definitions for Data Rows (local for simplicity) ---
    const redStyle: Partial<Style> = { ...STYLE_DEFAULT, font: { ...STYLE_DEFAULT.font, size: 12, color: { argb: 'FFFF0000' }, bold: true }, fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF8D7DA' } } as Fill };
    const boldStyle: Partial<Style> = { ...STYLE_DEFAULT, font: { ...STYLE_DEFAULT.font, bold: true, color: { argb: 'FF000000' } }, alignment: STYLE_ALIGNMENT_LEFT };
    const totalRowStyle: Partial<Style> = { ...STYLE_DEFAULT, fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD9E1F2' } } as Fill };


    // Write Header
    const headerRowSheet = worksheet.getRow(currentRow);
    headerRowSheet.values = [''].concat(header);
    headerRowSheet.height = 20;
    headerRowSheet.eachCell((cell, colIndex) => {
        if (colIndex >= 1) { cell.style = STYLE_HEADER as ExcelJS.Style; }
    });
    currentRow++;

    // Write Data and Apply Styles
    data.forEach((row: any, rowIndex: number) => {
        const excelRow = currentRow + rowIndex;
        const newRow = worksheet.getRow(excelRow);
        newRow.values = [''].concat(row);
        newRow.height = 18;

        const isLastRow = rowIndex === data.length - 1;
        const isTotalRow = rowIndex === data.length - 2;
        const isGroupHeader = rowIndex === 0 || rowIndex === 2;

        for (let c = 1; c <= header.length; c++) {
            const cell = newRow.getCell(c);
            let currentStyle: Partial<Style> = STYLE_DEFAULT;
            const cellValue = row[c - 1];

            if (isLastRow) {
                currentStyle = redStyle;
            } else if (isTotalRow) {
                currentStyle = { ...boldStyle, fill: totalRowStyle.fill, alignment: c === 1 ? STYLE_ALIGNMENT_LEFT : STYLE_ALIGNMENT_RIGHT };
            } else if (isGroupHeader) {
                currentStyle = { ...boldStyle, font: { ...boldStyle.font, size: 12 }, alignment: STYLE_ALIGNMENT_LEFT };
            } else if (cellValue === null) {
                if (rowIndex === 1 || rowIndex === 4) {
                    currentStyle = { ...STYLE_DEFAULT, fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFFFFF' } } as Fill, border: {} };
                }
            }

            if (c === 1 && !isLastRow && !isTotalRow && !isGroupHeader) {
                currentStyle = { ...currentStyle, alignment: STYLE_ALIGNMENT_LEFT };
            }

            if (!currentStyle.fill) {
                currentStyle = { ...currentStyle, fill: STYLE_DEFAULT.fill };
            }

            cell.style = currentStyle as ExcelJS.Style;
        }
    });

    return currentRow + data.length - 1;
};

/**
 * Sheet 3: สถานะการกรอกรายเดือน
 */
const populateMemberMonthlySubmissionExport = (
    worksheet: ExcelJS.Worksheet,
    data: any,
    periods: any,
    startRow: number
): number => {
    let currentRow = startRow;
    const periodsToDisplay = periods.filter((p: any) => p.key !== 'TOTAL_PERIODS');

    const periodHeaders = periodsToDisplay.map((p: any) => p.label);

    const headerRow1 = ['รายชื่อสมาชิก', 'ประเภท', 'ยอดรวม', ...periodsToDisplay.map(() => 'สถานะการกรอก')];
    const headerRow2 = ['รายชื่อสมาชิก', 'ประเภท', 'ยอดรวม', ...periodHeaders];

    const dataRows = data.map((m: any) => {
        return [
            m.name,
            m.member_type,
            m.total_submitted_in_period.toLocaleString('th-TH'),
            ...periodsToDisplay.map((p: any) => m.submissions[p.key] || '-')
        ];
    });

    const headersCount = 2;

    // Write Headers
    const row1 = worksheet.getRow(currentRow);
    row1.values = [''].concat(headerRow1);
    row1.height = 20;
    row1.eachCell((cell) => { cell.style = STYLE_HEADER as ExcelJS.Style; });

    const row2 = worksheet.getRow(currentRow + 1);
    row2.values = [''].concat(headerRow2.map((h, i) => i < 3 ? '' : h));
    row2.height = 20;
    row2.eachCell((cell) => { cell.style = STYLE_HEADER as ExcelJS.Style; });
    currentRow += headersCount;

    // Apply Merges
    const startMergeRow = startRow;
    if (periodsToDisplay.length > 1) {
        worksheet.mergeCells(startMergeRow, 4, startMergeRow, 3 + periodsToDisplay.length);
    }
    worksheet.mergeCells(startMergeRow, 1, startMergeRow + 1, 1);
    worksheet.mergeCells(startMergeRow, 2, startMergeRow + 1, 2);
    worksheet.mergeCells(startMergeRow, 3, startMergeRow + 1, 3);

    // --- Style Definitions for Data Rows ---
    const successStyle: Partial<Style> = { ...STYLE_DEFAULT, alignment: STYLE_ALIGNMENT_CENTER, font: { ...STYLE_DEFAULT.font, color: { argb: 'FF00B050' }, bold: true } };
    const errorStyle: Partial<Style> = { ...STYLE_DEFAULT, alignment: STYLE_ALIGNMENT_CENTER, font: { ...STYLE_DEFAULT.font, color: { argb: 'FFFF0000' } } };
    const boldStyle: Partial<Style> = { ...STYLE_DEFAULT, font: { ...STYLE_DEFAULT.font, bold: true }, alignment: STYLE_ALIGNMENT_LEFT };


    // Write Data and Apply Styles
    dataRows.forEach((row: any, rowIndex: number) => {
        const excelRow = currentRow + rowIndex;
        const newRow = worksheet.getRow(excelRow);
        newRow.values = [''].concat(row);
        newRow.height = 18;

        // Name/Type/Total columns (1, 2, 3)
        for (let c = 1; c <= 3; c++) {
            const cell = newRow.getCell(c);
            let currentStyle: Partial<Style> = STYLE_DEFAULT;
            if (c === 1) {
                currentStyle = boldStyle;
            } else if (c === 2) {
                currentStyle = { ...STYLE_DEFAULT, alignment: STYLE_ALIGNMENT_LEFT };
            } else if (c === 3) {
                currentStyle = { ...boldStyle, alignment: STYLE_ALIGNMENT_RIGHT };
            }
            cell.style = currentStyle as ExcelJS.Style;
        }

        // Status columns (4+)
        for (let c = 4; c <= newRow.values.length; c++) {
            const cell = newRow.getCell(c);
            const value = row[c - 1];

            if (value === 'X') {
                cell.style = successStyle as ExcelJS.Style;
            } else if (value === '-') {
                cell.style = errorStyle as ExcelJS.Style;
            } else {
                cell.style = STYLE_DEFAULT as ExcelJS.Style;
            }
        }
    });

    return currentRow + dataRows.length - 1;
};


// --------------------------------------------------------------------
// 3. MAIN EXPORT FUNCTION (ต้องอยู่ล่างสุด)
// --------------------------------------------------------------------
const exportToExcel = async () => {
    const workbook = new ExcelJS.Workbook();
    // Assuming chartSubtitle.value is defined in the surrounding scope
    const chartSubtitleText = chartSubtitle.value || 'รายงานรวมตามปีปัจจุบัน';

    const sheetConfigurations = [
        // อ้างอิงถึงฟังก์ชันที่ถูกประกาศไว้ข้างบนแล้ว
        { name: '1. อัตราเติบโต (มูลค่าบ้าน)', data: growthRateReportTableData.value, periods: tablePeriods.value, isSummary: true, populateFn: populateGrowthRateReportExport, isRegionCategory: false, isRegionAndCategory: false },
        { name: '2. สรุปสถานะสมาชิก', data: memberSubmissionSummary.value, periods: [], isSummary: true, populateFn: populateMemberSummaryReportExport, isRegionCategory: false, isRegionAndCategory: false },
        { name: '3. สถานะการกรอกรายเดือน', data: memberMonthlySubmissionTableData.value, periods: tablePeriods.value, isSummary: false, populateFn: populateMemberMonthlySubmissionExport, isRegionCategory: false, isRegionAndCategory: false },
        { name: '4. สรุปตามมูลค่าบ้าน', data: monthlyReportTableData.value, periods: tablePeriods.value, isSummary: false, isRegionCategory: false, isRegionAndCategory: false, populateFn: populateVerticalTableExport },
        { name: '5. สรุปตามภูมิภาค', data: regionReportTableData.value, periods: tablePeriods.value, isSummary: false, isRegionCategory: true, isRegionAndCategory: false, populateFn: populateVerticalTableExport },
        { name: '6. สรุปตามภูมิภาคและมูลค่า', data: regionAndCategoryReportTableData.value, periods: tablePeriods.value, isSummary: false, isRegionCategory: true, isRegionAndCategory: true, populateFn: populateVerticalTableExport },
    ];

    const defaultTitleStyle: Partial<Style> = { font: { name: COMMON_FONT_NAME, size: 18, bold: true, color: { argb: "FF3F51B5" } }, alignment: STYLE_ALIGNMENT_CENTER };
    const defaultSubtitleStyle: Partial<Style> = { font: { name: COMMON_FONT_NAME, size: 14, bold: true, color: { argb: "FF000000" } }, alignment: STYLE_ALIGNMENT_CENTER };

    for (const config of sheetConfigurations) {
        if (!config.data) continue;

        const worksheet = workbook.addWorksheet(config.name.substring(0, 31));
        const reportTitle = `รายงานยอดเซ็นสัญญา: ${config.name.replace(/^[0-9]\. /g, '').replace(/\(.+\)/g, '').trim()}`;
        const subTitle = chartSubtitleText.startsWith('กรุณา') ? 'รายงานรวมตามปีปัจจุบัน' : chartSubtitleText;

        // --- Determine Max Columns for Title Merging ---
        const periodsData = config.periods || [];
        let maxCols: number;

        if (config.name.includes('สถานะสมาชิก')) {
            maxCols = 2;
        } else if (config.name.includes('สถานะการกรอก')) {
            maxCols = 3 + periodsData.length;
        } else {
            const numPeriods = periodsData.length;
            const hasGrowth = numPeriods > 0 && periodsData.some((p: any) => p.monthIndex !== undefined);
            const baseCols = config.name.includes('อัตราเติบโต') ? 2 : (config.isRegionAndCategory ? 3 : 2);
            maxCols = baseCols + numPeriods + (hasGrowth ? 2 : 0);
        }

        // 1. Write and Merge Title/Subtitle
        const titleRow = worksheet.insertRow(1, [reportTitle]);
        const subtitleRow = worksheet.insertRow(2, [subTitle]);
        worksheet.insertRow(3, []);

        worksheet.mergeCells(1, 1, 1, maxCols);
        worksheet.mergeCells(2, 1, 2, maxCols);

        // Apply Title/Subtitle Style
        worksheet.getCell(1, 1).style = defaultTitleStyle as ExcelJS.Style;
        worksheet.getCell(2, 1).style = defaultSubtitleStyle as ExcelJS.Style;
        titleRow.height = 24;
        subtitleRow.height = 20;

        // 2. Populate Data and Apply Column Widths

        // --- Setup Column Widths ---
        let columns: Partial<ExcelJS.Column>[] = [{ width: 5 }]; // Col 0 (Unused/Spacer)

        const numPeriods = periodsData.length;
        const hasGrowth = numPeriods > 0 && periodsData.some((p: any) => p.monthIndex !== undefined);

        if (config.name.includes('อัตราเติบโต')) {
            columns.push({ width: 30 }, { width: 40 });
            periodsData.forEach(() => columns.push({ width: 18 }));
        } else if (config.name.includes('สถานะสมาชิก')) {
            columns.push({ width: 45 }, { width: 20 });
        } else if (config.name.includes('สถานะการกรอก')) {
            columns.push({ width: 30 }, { width: 20 }, { width: 15 });
            periodsData.forEach(() => columns.push({ width: 10 }));
        } else {
            // Vertical Tables (Sheets 4, 5, 6)
            columns.push({ width: 30 });
            if (config.isRegionAndCategory) { columns.push({ width: 25 }); }
            columns.push({ width: 25 });
            periodsData.forEach(() => columns.push({ width: 15 }));
            if (hasGrowth) { columns.push({ width: 12 }, { width: 12 }); }
        }
        worksheet.columns = columns as ExcelJS.Column[];


        // --- Populate Data ---
        config.populateFn(worksheet, config.data, config.periods, showQoQ.value, TITLE_ROWS + 1);
    }

    // 4. สร้างไฟล์และดาวน์โหลด
    const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
    const filename = `HBA_Report_${dateStr}.xlsx`;

    // Save the file
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
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
                            <v-btn color="success" prepend-icon="mdi-microsoft-excel" @click="exportToExcel">
                                Export Excel
                            </v-btn>
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
