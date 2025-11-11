<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useDate } from 'vuetify/lib/framework.mjs';
const date = useDate();

// *** [เพิ่ม]: นำเข้าไลบรารี Excel ***
// คุณต้องติดตั้งไลบรารีนี้ก่อน (e.g., npm install xlsx)
import * as XLSX from 'xlsx';
// *** [สิ้นสุดส่วนเพิ่ม] ***

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


// ====================================================================
// *** [ส่วนที่เพิ่ม]: ฟังก์ชันและเมธอดสำหรับการ Export Excel (Vertical Structure + Styling) ***
// ====================================================================

interface ExportResult {
    data: any[][];
    merges: any[];
    cols?: { wch: number }[];
    customStyle?: (ws: XLSX.WorkSheet, headerRows: number) => void;
    headerRows: number;
}

// --------------------------------------------------------------------
// 1. Helper Function: สำหรับการแปลงตารางสรุป (Vertical) และคำนวณ Merge
// --------------------------------------------------------------------
// ใช้สำหรับ Sheet 4, 5, 6
const createVerticalTableExport = (
    tableData: TableCategory[] | RegionCategoryGroup[],
    periods: PeriodItem[],
    isRegionCategory: boolean = false,
    isRegionAndCategory: boolean = false
): ExportResult => {
    const finalData: any[][] = [];
    const merges: any[] = [];
    const periodsToDisplay = periods.filter(p => p.key !== 'TOTAL_PERIODS');
    const lp = periodsToDisplay.pop() || null;

    // --- 1. Header Generation ---
    let headerRow: any[] = ['กลุ่มหลัก', 'รายละเอียด', ...periods.map(p => p.label)];
    let finalHeaders: any[][] = [];

    // Adjust header for different table types
    if (isRegionAndCategory) {
        finalHeaders.push(['ภูมิภาค', 'ช่วงมูลค่าบ้าน', 'รายละเอียด', ...periods.map(p => p.label)]); // Row 0
        finalHeaders.push(['', '', '', ...periods.map(p => p.label)]); // Row 1 (for MoM/YTD label)
    } else if (!isRegionCategory) { // Monthly Report Table (Sheet 4)
        finalHeaders.push(['มูลค่าบ้าน', 'รายละเอียด', ...periods.map(p => p.label)]); // Row 0
        finalHeaders.push(['', '', ...periods.map(p => p.label)]); // Row 1 (for MoM/YTD label)
    } else { // Region Report Table (Sheet 5)
        finalHeaders.push(['ภูมิภาค', 'รายละเอียด', ...periods.map(p => p.label)]); // Row 0
        finalHeaders.push(['', '', ...periods.map(p => p.label)]); // Row 1 (for MoM/YTD label)
    }

    const hasGrowth = lp && lp.monthIndex;
    if (hasGrowth) {
        finalHeaders[0].push('MoM%', 'YTD%');
        finalHeaders[1].push('อัตราเติบโต', 'อัตราเติบโต');
    }

    // --- 2. Data Row Generation and Merges ---
    let currentRow = 0; // Relative to data rows (excluding finalHeaders)
    let headerRowsCount = finalHeaders.length;

    (tableData as any[]).forEach((group: TableCategory | RegionCategoryGroup, groupIndex: number) => {
        const primaryGroupKey = isRegionAndCategory ? (group as RegionCategoryGroup).regionName : (group as TableCategory).categoryName;

        const secondaryGroups = isRegionAndCategory ? (group as RegionCategoryGroup).categories : [group as TableCategory];

        secondaryGroups.forEach((category, categoryIndex) => {
            const secondaryGroupKey = category.categoryName;
            const groupRowCount = category.rows.length;

            category.rows.forEach((row, rowIndex) => {
                const isTotalRow = primaryGroupKey === 'รวมทั่วประเทศ' || secondaryGroupKey === 'รวม' || primaryGroupKey === 'รวม';

                let rowData: any[] = [];

                let colIndex = 0;

                // Col 0: Primary Group (Region or Price Range)
                if (rowIndex === 0 && (!isRegionAndCategory || categoryIndex === 0)) {
                    const mergeSpan = isRegionAndCategory ? (group as RegionCategoryGroup).categories.length * groupRowCount : groupRowCount;
                    merges.push({ s: { r: currentRow + headerRowsCount, c: 0 }, e: { r: currentRow + headerRowsCount + mergeSpan - 1, c: 0 } });
                    rowData.push(primaryGroupKey);
                } else {
                    rowData.push('');
                }
                colIndex++;

                // Col 1: Secondary Group (Price Range or Metric Name)
                if (isRegionAndCategory) {
                    if (rowIndex === 0) {
                        const mergeSpan = groupRowCount;
                        merges.push({ s: { r: currentRow + headerRowsCount, c: 1 }, e: { r: currentRow + headerRowsCount + mergeSpan - 1, c: 1 } });
                        rowData.push(secondaryGroupKey);
                    } else {
                        rowData.push('');
                    }
                    colIndex++;
                }

                // Metric Name Column (Col 1 or 2)
                rowData.push(row.metricName);
                colIndex++;

                // Period Data Columns
                periods.forEach(p => {
                    const value = row.data[p.key] || 0;
                    rowData.push(row.format(value).replace(/,/g, ''));
                });

                // Growth Columns (MoM, YTD)
                if (hasGrowth) {
                    const momRate = row.growth.mom;
                    const ytdRate = row.growth.ytd;

                    // MoM Cell
                    rowData.push(momRate !== null ? `${momRate.toFixed(2)}%` : '-');

                    // YTD Cell
                    rowData.push(ytdRate !== null ? `${ytdRate.toFixed(2)}%` : '-');
                }

                finalData.push(rowData);
                currentRow++;
            });
        });
    });

    // --- 3. Final Assembly and Styling ---

    // Calculate final merge for MoM/YTD header if needed
    if (hasGrowth) {
        const momYtdStartCol = finalHeaders[0].length - 2;
        merges.push({ s: { r: 1 + 3, c: momYtdStartCol }, e: { r: 1 + 3, c: momYtdStartCol + 1 } });
    }

    // Styles for TH Sarabun PSK and Bold 
    const customStyle = (ws: XLSX.WorkSheet, startRow: number) => {
        const fontName = 'TH Sarabun PSK';
        const defaultStyle = { font: { name: fontName, sz: 11, color: { rgb: "000000" } }, border: { top: { style: "thin" }, bottom: { style: "thin" }, left: { style: "thin" }, right: { style: "thin" } }, alignment: { vertical: "center", horizontal: "right" } };
        const headerStyle = { ...defaultStyle, font: { name: fontName, sz: 12, bold: true }, fill: { fgColor: { rgb: "E9ECEF" } }, alignment: { vertical: "center", horizontal: "center" } };
        const groupStyle = { ...defaultStyle, font: { name: fontName, sz: 11, bold: true }, fill: { fgColor: { rgb: "FFFFFF" } }, alignment: { vertical: "center", horizontal: "left" } };
        const totalStyle = { ...defaultStyle, font: { name: fontName, sz: 11, bold: true }, fill: { fgColor: { rgb: "F0F0F0" } } };
        const primaryTotalStyle = { ...totalStyle, font: { name: fontName, sz: 11, bold: true, color: { rgb: "3F51B5" } } }; // text-primary
        const successStyle = { ...defaultStyle, font: { name: fontName, sz: 11, color: { rgb: "28A745" } } }; // text-success
        const errorStyle = { ...defaultStyle, font: { name: fontName, sz: 11, color: { rgb: "DC3545" } } }; // text-error

        // Apply header styles and borders
        for (let r = 0; r < headerRowsCount; r++) {
            for (let c = 0; c < finalHeaders[r].length; c++) {
                const cellRef = XLSX.utils.encode_cell({ r: r + startRow, c: c });
                if (ws[cellRef]) { ws[cellRef].s = headerStyle; }
            }
        }

        // Apply data row styles and growth rate colors
        finalData.forEach((row, rowIndex) => {
            const excelRow = rowIndex + headerRowsCount + startRow;
            const primaryGroup = row[0];
            const secondaryGroup = isRegionAndCategory ? row[1] : '';
            const metricName = isRegionAndCategory ? row[2] : row[1];

            const isPrimaryTotal = primaryGroup === 'รวมทั่วประเทศ' || primaryGroup === 'รวม';
            const isSecondaryTotal = secondaryGroup === 'รวม';
            const isTotalMetric = metricName === 'มูลค่ารวม (บาท)';

            const baseRowStyle = isPrimaryTotal || isSecondaryTotal ? totalStyle : defaultStyle;
            let currentGroupIndex = 0;

            // Col 0: Primary Group
            const cellRef0 = XLSX.utils.encode_cell({ r: excelRow, c: 0 });
            if (row[0] !== '') {
                ws[cellRef0].s = isPrimaryTotal ? primaryTotalStyle : groupStyle;
            } else if (ws[cellRef0]) {
                ws[cellRef0].s = baseRowStyle; // Apply borders even if empty
            }
            currentGroupIndex++;

            // Col 1: Secondary Group (if applicable)
            if (isRegionAndCategory) {
                const cellRef1 = XLSX.utils.encode_cell({ r: excelRow, c: 1 });
                if (row[1] !== '') {
                    ws[cellRef1].s = isSecondaryTotal ? primaryTotalStyle : groupStyle;
                } else if (ws[cellRef1]) {
                    ws[cellRef1].s = baseRowStyle; // Apply borders
                }
                currentGroupIndex++;
            }

            // Col (1 or 2): Metric Name
            const cellRefMetric = XLSX.utils.encode_cell({ r: excelRow, c: currentGroupIndex });
            ws[cellRefMetric].s = { ...groupStyle, horizontal: "left", font: { ...groupStyle.font, bold: false } };
            currentGroupIndex++;

            // Period Data Columns (Numbers)
            for (let c = currentGroupIndex; c < row.length - (hasGrowth ? 2 : 0); c++) {
                const cellRef = XLSX.utils.encode_cell({ r: excelRow, c: c });
                if (ws[cellRef]) {
                    let style = { ...baseRowStyle, numFmt: (metricName.includes('(บาท)')) ? '#,##0.00' : '#,##0' };
                    if (isPrimaryTotal && isTotalMetric) {
                        style = { ...style, font: { ...style.font, color: primaryTotalStyle.font.color, bold: true } };
                    }
                    ws[cellRef].s = style;
                }
            }

            // MoM/YTD Columns (Growth)
            if (hasGrowth) {
                const momCol = row.length - 2;
                const ytdCol = row.length - 1;

                const momValue = row[momCol];
                const ytdValue = row[ytdCol];

                // MoM Styling
                const momRef = XLSX.utils.encode_cell({ r: excelRow, c: momCol });
                if (momValue !== '-') {
                    ws[momRef].s = momValue.includes('-') ? errorStyle : successStyle;
                } else if (ws[momRef]) { ws[momRef].s = baseRowStyle; }

                // YTD Styling
                const ytdRef = XLSX.utils.encode_cell({ r: excelRow, c: ytdCol });
                if (ytdValue !== '-') {
                    ws[ytdRef].s = ytdValue.includes('-') ? errorStyle : successStyle;
                } else if (ws[ytdRef]) { ws[ytdRef].s = baseRowStyle; }
            }
        });
    };

    // Calculate column widths
    const cols = [{ wch: 25 }]; // Primary Group (Default)
    if (isRegionAndCategory) {
        cols.push({ wch: 18 }); // Secondary Group
        cols.push({ wch: 20 }); // Metric Name
    } else {
        cols.push({ wch: 20 }); // Metric Name
    }
    periods.forEach(() => cols.push({ wch: 12 })); // Period columns
    if (hasGrowth) {
        cols.push({ wch: 10 }, { wch: 10 }); // MoM, YTD
    }

    return { data: finalHeaders.concat(finalData), merges: merges, cols: cols, customStyle: customStyle, headerRows: headerRowsCount };
};

// --------------------------------------------------------------------
// 2. Vertical Table Wrappers (Sheet 4, 5, 6)
// --------------------------------------------------------------------

// Sheet 4
const getMonthlyReportDataForExport = (): { [key: string]: ExportResult } => {
    const table = createVerticalTableExport(monthlyReportTableData.value, tablePeriods.value, false, false);
    return { 'สรุปตามมูลค่าบ้าน': table };
};

// Sheet 5
const getRegionReportDataForExport = (): { [key: string]: ExportResult } => {
    const table = createVerticalTableExport(regionReportTableData.value, tablePeriods.value, true, false);
    return { 'สรุปตามภูมิภาค': table };
};

// Sheet 6
const getRegionCategoryReportDataForExport = (): { [key: string]: ExportResult } => {
    const table = createVerticalTableExport(regionAndCategoryReportTableData.value, tablePeriods.value, true, true);
    return { 'สรุปตามภูมิภาคและมูลค่า': table };
};

// --------------------------------------------------------------------
// 3. ตารางอัตราการเติบโต (Sheet 1) - Vertical Metrics (ปรับจาก Transposed)
// --------------------------------------------------------------------
const getGrowthRateReportDataForExport = (): { [key: string]: ExportResult } => {
    const data = growthRateReportTableData.value;
    const periods = tablePeriods.value.filter(p => p.key !== 'TOTAL_PERIODS');
    const showQoQValue = showQoQ.value;

    if (!data || periods.length === 0) return {};

    const metricsToInclude: { key: MetricGrowthKey, name: string }[] = [
        { key: 'total_units', name: 'จำนวนหลัง' },
        { key: 'total_value', name: 'มูลค่ารวม (บาท)' },
    ];

    const metricGroups = [...valueCategories, 'รวม'];

    const header = ['ช่วงมูลค่าบ้าน', 'ตัวชี้วัด', ...periods.map(p => p.label)];
    const headersCount = 1;
    const finalData: any[][] = [header];
    const merges: any[] = [];
    let currentRow = 1;

    metricGroups.forEach(categoryName => {
        const categoryData = growthRateReportTableData.value.find(c => c.categoryName === categoryName);
        if (!categoryData) return;

        const startRow = currentRow;
        let rowCount = 0;

        metricsToInclude.forEach((metric) => {
            const metricKey = metric.key;
            const formatFn = metricRows.find(r => r.key === metricKey)!.format;
            const growthKeys: { key: keyof GrowthRateMetrics, label: string }[] = [
                { key: 'Raw', label: metric.name + ' (ค่าดิบ)' },
                { key: 'MoM', label: 'MoM%' },
                { key: 'YoY', label: 'YoY%' },
                ...(showQoQValue ? [{ key: 'QoQ' as keyof GrowthRateMetrics, label: 'QoQ%' }] : []),
                { key: 'YTD', label: 'YTD%' },
            ];

            growthKeys.forEach((g) => {
                const row: any[] = [];
                const totalRowsPerGroup = metricsToInclude.length * growthKeys.length;

                // Col 0: Category Name (Merged)
                if (rowCount === 0) {
                    merges.push({ s: { r: startRow, c: 0 }, e: { r: startRow + totalRowsPerGroup - 1, c: 0 } });
                    row.push(categoryName);
                } else {
                    row.push('');
                }

                // Col 1: Indicator Name
                row.push(g.label);

                // Cols 2+: Period Values
                periods.forEach(p => {
                    if (g.key === 'Raw') {
                        const value = categoryData[`${metricKey}_raw` as 'total_value_raw' | 'total_units_raw'][p.key] || 0;
                        row.push(formatFn(value).replace(/,/g, ''));
                    } else {
                        const rate = categoryData[metricKey][p.key][g.key as keyof GrowthRateMetrics];
                        row.push(rate !== null ? `${rate.toFixed(2)}%` : '-');
                    }
                });

                finalData.push(row);
                currentRow++;
                rowCount++;
            });
        });
    });

    const customStyleGrowth = (ws: XLSX.WorkSheet, startRow: number) => {
        const fontName = 'TH Sarabun PSK';
        const defaultStyle = { font: { name: fontName, sz: 11 }, alignment: { horizontal: 'right', vertical: "center" }, border: { top: { style: "thin" }, bottom: { style: "thin" }, left: { style: "thin" }, right: { style: "thin" } } };
        const headerStyle = { ...defaultStyle, font: { name: fontName, sz: 12, bold: true }, fill: { fgColor: { rgb: "E9ECEF" } }, alignment: { vertical: "center", horizontal: "center" } };
        const groupStyle = { ...defaultStyle, font: { name: fontName, sz: 11, bold: true }, fill: { fgColor: { rgb: "FFFFFF" } }, alignment: { vertical: "center", horizontal: "left" } };
        const greenStyle = { ...defaultStyle, font: { name: fontName, sz: 11, color: { rgb: "28A745" } } };
        const redStyle = { ...defaultStyle, font: { name: fontName, sz: 11, color: { rgb: "DC3545" } } };
        const totalStyle = { ...defaultStyle, fill: { fgColor: { rgb: "F0F0F0" } }, font: { name: fontName, sz: 11, bold: true } };

        // Apply header styles 
        for (let c = 0; c < finalData[0].length; c++) {
            const cellRef = XLSX.utils.encode_cell({ r: startRow, c: c });
            if (ws[cellRef]) { ws[cellRef].s = headerStyle; }
        }

        // Apply data row styles and colors
        finalData.slice(1).forEach((row, rowIndex) => {
            const excelRow = rowIndex + headersCount + startRow;
            const categoryName = row[0];
            const indicatorName = row[1];

            const isTotalCategory = categoryName === 'รวม';

            // Col 0: Category Group
            const cellRef0 = XLSX.utils.encode_cell({ r: excelRow, c: 0 });
            if (row[0] !== '') {
                ws[cellRef0].s = isTotalCategory ? totalStyle : groupStyle;
            } else if (ws[cellRef0]) { ws[cellRef0].s = isTotalCategory ? totalStyle : defaultStyle; }

            // Col 1: Indicator Name
            const cellRef1 = XLSX.utils.encode_cell({ r: excelRow, c: 1 });
            ws[cellRef1].s = isTotalCategory ? totalStyle : groupStyle;

            // Cols 2+: Period Values (Growth Rates)
            for (let c = 2; c < row.length; c++) {
                const cellRef = XLSX.utils.encode_cell({ r: excelRow, c: c });
                if (ws[cellRef]) {
                    const value = row[c];
                    let style = isTotalCategory ? totalStyle : defaultStyle;

                    if (indicatorName.includes('ค่าดิบ')) {
                        style = { ...style, numFmt: (indicatorName.includes('(บาท)')) ? '#,##0.00' : '#,##0' };
                    } else if (indicatorName.includes('%')) { // Growth Rate columns
                        if (value !== '-' && parseFloat(value) > 0) { style = greenStyle; }
                        else if (value !== '-' && parseFloat(value) < 0) { style = redStyle; }
                    }
                    ws[cellRef].s = style;
                }
            }
        });
    };

    const cols: { wch: number }[] = [{ wch: 20 }, { wch: 30 }];
    periods.forEach(() => cols.push({ wch: 15 }));

    // Return as a single sheet (Summary of growth rates)
    return { 'อัตราเติบโต (สรุป)': { data: finalData, merges: merges, cols: cols, customStyle: customStyleGrowth, headerRows: 1 } };
};

// --------------------------------------------------------------------
// 4. ตารางสถานะการกรอก (Sheet 2) - Member Summary (Non-Transposed/Vertical)
// --------------------------------------------------------------------
const getMemberSummaryReportDataForExport = (): { [key: string]: ExportResult } => {
    const summary = memberSubmissionSummary.value;
    const periods = summary.periodCounts;

    const header = ['รายละเอียด', 'จำนวนสมาชิก (คน)'];
    const data: any[][] = [
        ['สมาชิกทั้งหมด (ประเภท User)', summary.totalUsers.toLocaleString('th-TH')],
        ['', ''],
        ['จำนวนสมาชิกที่กรอกในแต่ละช่วงเวลา:', ''],
        ...periods.map(p => [`> ${p.label}`, p.count.toLocaleString('th-TH')]),
        ['', ''],
        [summary.submittedLabel, summary.submittedTotal.toLocaleString('th-TH')],
        [summary.notSubmittedLabel, summary.notSubmittedTotal.toLocaleString('th-TH')]
    ];

    const cols = [{ wch: 40 }, { wch: 15 }];

    const customStyleMemberSummary = (ws: XLSX.WorkSheet, startRow: number) => {
        const fontName = 'TH Sarabun PSK';
        const defaultStyle = { font: { name: fontName, sz: 11 }, border: { top: { style: "thin" }, bottom: { style: "thin" }, left: { style: "thin" }, right: { style: "thin" } }, alignment: { vertical: "center", horizontal: "right" } };
        const headerStyle = { ...defaultStyle, font: { name: fontName, sz: 12, bold: true }, fill: { fgColor: { rgb: "E9ECEF" } }, alignment: { vertical: "center", horizontal: "center" } };
        const redStyle = { ...defaultStyle, font: { name: fontName, sz: 11, color: { rgb: "DC3545" }, bold: true }, fill: { fgColor: { rgb: "F8D7DA" } } }; // bg-red-lighten-5
        const boldStyle = { ...defaultStyle, font: { name: fontName, sz: 11, bold: true }, alignment: { vertical: "center", horizontal: "left" } };

        // Apply header styles
        for (let c = 0; c < header.length; c++) {
            const cellRef = XLSX.utils.encode_cell({ r: startRow, c: c });
            if (ws[cellRef]) { ws[cellRef].s = headerStyle; }
        }

        // Apply red/bold style to the last row (not submitted) and bold to key rows
        data.forEach((row, rowIndex) => {
            const excelRow = rowIndex + 1 + startRow;
            const isLastRow = rowIndex === data.length - 1;
            const isBoldRow = rowIndex === 0 || rowIndex === 3 || rowIndex === 6;

            for (let c = 0; c < header.length; c++) {
                const cellRef = XLSX.utils.encode_cell({ r: excelRow, c: c });
                if (ws[cellRef]) {
                    if (isLastRow) {
                        ws[cellRef].s = redStyle;
                    } else if (isBoldRow) {
                        ws[cellRef].s = c === 0 ? boldStyle : { ...defaultStyle, font: boldStyle.font };
                    } else if (row[c] !== '') {
                        ws[cellRef].s = defaultStyle;
                    } else {
                        ws[cellRef].s = { ...defaultStyle, border: { top: { style: "none" }, bottom: { style: "none" }, left: { style: "none" }, right: { style: "none" } } }; // Hide border for blank row
                    }
                    if (c === 0 && !isLastRow) {
                        ws[cellRef].s = { ...(ws[cellRef].s || defaultStyle), horizontal: "left" };
                    }
                }
            }
        });
    };


    return { 'สรุปสถานะสมาชิก': { data: [header, ...data], merges: [], cols: cols, customStyle: customStyleMemberSummary, headerRows: 1 } };
};


// --------------------------------------------------------------------
// 5. ตารางสถานะการกรอกรายเดือน (Sheet 3) - Member Monthly Submission (Non-Transposed)
// --------------------------------------------------------------------
const getMemberMonthlySubmissionDataForExport = (): { [key: string]: ExportResult } => {
    const data = memberMonthlySubmissionTableData.value;
    const periods = tablePeriods.value.filter(p => p.key !== 'TOTAL_PERIODS');

    const periodHeaders = periods.map(p => p.label);

    // Header Row 1: Merge 'สถานะการกรอก' over periods
    const headerRow1 = ['รายชื่อสมาชิก', 'ประเภท', 'ยอดรวม', ...periods.map(() => 'สถานะการกรอก')];
    // Header Row 2: Period Labels
    const headerRow2 = ['รายชื่อสมาชิก', 'ประเภท', 'ยอดรวม', ...periodHeaders];

    const dataRows = data.map(m => {
        return [
            m.name,
            m.member_type,
            m.total_submitted_in_period.toLocaleString('th-TH'),
            ...periods.map(p => m.submissions[p.key] || '-')
        ];
    });

    const merges: any[] = [];
    // Merge periods (Horizontal)
    if (periods.length > 1) {
        merges.push({ s: { r: 0, c: 3 }, e: { r: 0, c: 3 + periods.length - 1 } });
    }

    // Merge first three columns (Vertical)
    merges.push({ s: { r: 0, c: 0 }, e: { r: 1, c: 0 } });
    merges.push({ s: { r: 0, c: 1 }, e: { r: 1, c: 1 } });
    merges.push({ s: { r: 0, c: 2 }, e: { r: 1, c: 2 } });

    const finalAOAData = [headerRow1, headerRow2.map((h, i) => i < 3 ? '' : h), ...dataRows];

    const cols = [{ wch: 25 }, { wch: 15 }, { wch: 10 }];
    periods.forEach(() => cols.push({ wch: 8 }));

    const customStyleMemberMonthly = (ws: XLSX.WorkSheet, startRow: number) => {
        const fontName = 'TH Sarabun PSK';
        const defaultStyle = { font: { name: fontName, sz: 11 }, alignment: { horizontal: 'center', vertical: "center" }, border: { top: { style: "thin" }, bottom: { style: "thin" }, left: { style: "thin" }, right: { style: "thin" } } };
        const headerStyle = { ...defaultStyle, font: { name: fontName, sz: 12, bold: true }, fill: { fgColor: { rgb: "E9ECEF" } } };
        const successStyle = { ...defaultStyle, font: { name: fontName, sz: 11, color: { rgb: "28A745" }, bold: true } }; // text-success
        const errorStyle = { ...defaultStyle, font: { name: fontName, sz: 11, color: { rgb: "DC3545" } } }; // text-error
        const boldStyle = { ...defaultStyle, font: { name: fontName, sz: 11, bold: true }, alignment: { horizontal: "left", vertical: "center" } };

        // Apply header styles
        for (let r = 0; r < 2; r++) {
            for (let c = 0; c < finalAOAData[r].length; c++) {
                const cellRef = XLSX.utils.encode_cell({ r: r + startRow, c: c });
                if (ws[cellRef]) { ws[cellRef].s = headerStyle; }
            }
        }

        // Apply data row styles (X/ - colors)
        dataRows.forEach((row, rowIndex) => {
            const excelRow = rowIndex + 2 + startRow; // +2 for 2 header rows

            // Name/Type/Total columns
            for (let c = 0; c < 3; c++) {
                const cellRef = XLSX.utils.encode_cell({ r: excelRow, c: c });
                if (ws[cellRef]) {
                    ws[cellRef].s = (c === 0 || c === 2) ? boldStyle : defaultStyle;
                    if (c === 2) { ws[cellRef].s = { ...(ws[cellRef].s || defaultStyle), horizontal: "right" }; } // align right for total
                    if (c === 1) { ws[cellRef].s = { ...(ws[cellRef].s || defaultStyle), horizontal: "left" }; }
                }
            }

            // Status columns (X / -)
            for (let c = 3; c < row.length; c++) {
                const cellRef = XLSX.utils.encode_cell({ r: excelRow, c: c });
                if (ws[cellRef]) {
                    if (row[c] === 'X') {
                        ws[cellRef].s = successStyle;
                    } else if (row[c] === '-') {
                        ws[cellRef].s = errorStyle;
                    } else {
                        ws[cellRef].s = defaultStyle;
                    }
                }
            }
        });
    };

    return { 'สถานะการกรอกรายเดือน': { data: finalAOAData, merges: merges, cols: cols, customStyle: customStyleMemberMonthly, headerRows: 2 } };
};

// --------------------------------------------------------------------
// 6. ฟังก์ชันหลักสำหรับรวมและ Export
// --------------------------------------------------------------------
const exportToExcel = () => {
    // 1. เตรียมข้อมูลสำหรับแต่ละชีต
    const sheet1 = getGrowthRateReportDataForExport();       // อัตราเติบโต (แยกตามมูลค่าบ้าน)
    const sheet4 = getMonthlyReportDataForExport();          // ตารางสรุปยอดเซ็นสัญญา แยกตามมูลค่าบ้าน (Vertical)
    const sheet5 = getRegionReportDataForExport();           // ตารางสรุปยอดเซ็นสัญญา แยกตามภูมิภาค (Vertical)
    const sheet6 = getRegionCategoryReportDataForExport();   // ตารางสรุปยอดเซ็นสัญญา แยกตามภูมิภาคและมูลค่า (Vertical)
    const sheet2 = getMemberSummaryReportDataForExport();    // รายงานสถานะการกรอกสัญญาของสมาชิก (Vertical)
    const sheet3 = getMemberMonthlySubmissionDataForExport(); // ตารางสถานะการกรอกสัญญาต่อเดือน (Vertical)


    // 2. จัดเรียงลำดับชีตตามที่ user ต้องการ: 1, 2, 3, 4, 5, 6
    const allSheets: Record<string, ExportResult> = {
        '1. อัตราเติบโต (มูลค่าบ้าน)': sheet1['อัตราเติบโต (สรุป)'],
        '2. สรุปสถานะสมาชิก': sheet2['สรุปสถานะสมาชิก'],
        '3. สถานะการกรอกรายเดือน': sheet3['สถานะการกรอกรายเดือน'],
        '4. สรุปตามมูลค่าบ้าน': sheet4['สรุปตามมูลค่าบ้าน'],
        '5. สรุปตามภูมิภาค': sheet5['สรุปตามภูมิภาค'],
        '6. สรุปตามภูมิภาคและมูลค่า': sheet6['สรุปตามภูมิภาคและมูลค่า'],
    };


    if (Object.keys(allSheets).length === 0) {
        console.warn('No data available for export.');
        alert('ไม่พบข้อมูลสำหรับ Export กรุณาเลือกช่วงเวลาหรือตรวจสอบข้อมูล');
        return;
    }

    // 3. สร้าง Workbook และเพิ่มชีต
    const workbook = XLSX.utils.book_new();
    const chartSubtitleText = chartSubtitle.value;
    const blankRowsBeforeData = 3; // Title + Subtitle + Blank row

    for (const [sheetName, { data, merges, cols, customStyle, headerRows }] of Object.entries(allSheets)) {
        // เพิ่มแถวหัวข้อรายงาน (Title and Subtitle)
        const reportTitle = `รายงานยอดเซ็นสัญญา: ${sheetName.replace(/^[0-9]\. /g, '').replace(/\(.+\)/g, '').trim()}`;
        const subTitle = chartSubtitleText.startsWith('กรุณา') ? 'รายงานรวมตามปีปัจจุบัน' : chartSubtitleText;
        const reportHeaders = [
            [reportTitle], // Row 1: Title
            [subTitle],    // Row 2: Subtitle
            []             // Row 3: Blank row separator
        ];

        // ผสานเซลล์สำหรับ Title และ Subtitle
        const maxCols = data[0].length;
        const titleMerges = [
            { s: { r: 0, c: 0 }, e: { r: 0, c: maxCols - 1 } },
            { s: { r: 1, c: 0 }, e: { r: 1, c: maxCols - 1 } }
        ];

        // สร้าง Worksheet โดยใช้ Title/Subtitle นำหน้า
        const worksheet = XLSX.utils.aoa_to_sheet([...reportHeaders, ...data]);

        // เลื่อน merges ที่คำนวณไว้เดิมลงมา 3 แถว (บวก Title, Subtitle, ช่องว่าง)
        const shiftedMerges = merges.map(m => ({
            s: { r: m.s.r + blankRowsBeforeData, c: m.s.c },
            e: { r: m.e.r + blankRowsBeforeData, c: m.e.c }
        }));

        // รวม merges ทั้งหมดเข้าด้วยกัน
        worksheet['!merges'] = [...titleMerges, ...shiftedMerges];

        // กำหนดความกว้างคอลัมน์
        if (cols) {
            worksheet['!cols'] = cols;
        }

        // *** [สำคัญ]: ใช้ Custom Style Function เพื่อกำหนดฟอนต์และสี ***
        if (customStyle) {
            customStyle(worksheet, blankRowsBeforeData);
        }

        // กำหนด style ให้กับ Title/Subtitle 
        const fontName = 'TH Sarabun PSK';
        const defaultTitleStyle = { font: { name: fontName, sz: 14, bold: true }, alignment: { horizontal: 'center' } };
        const defaultSubtitleStyle = { font: { name: fontName, sz: 12 }, alignment: { horizontal: 'center' } };
        if (worksheet[XLSX.utils.encode_cell({ r: 0, c: 0 })]) {
            worksheet[XLSX.utils.encode_cell({ r: 0, c: 0 })].s = defaultTitleStyle;
        }
        if (worksheet[XLSX.utils.encode_cell({ r: 1, c: 0 })]) {
            worksheet[XLSX.utils.encode_cell({ r: 1, c: 0 })].s = defaultSubtitleStyle;
        }


        XLSX.utils.book_append_sheet(workbook, worksheet, sheetName.substring(0, 31));
    }

    // 4. สร้างไฟล์และดาวน์โหลด
    const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
    const filename = `HBA_Report_${dateStr}.xlsx`;
    XLSX.writeFile(workbook, filename);
};</script>

<template>
    <v-row>

        <v-snackbar v-model="showSnackbar" :timeout="snackbarTimeout" :color="snackbarColor" location="top end"
            variant="elevated">
            {{ snackbarText }}
            <template v-slot:actions>
                <v-btn color="white" variant="text" @click="showSnackbar = false">
                    ปิด
                </v-btn>
            </template>
        </v-snackbar>


        <v-col cols="12" sm="12" lg="12">
            <div class="mt-3 mb-6">
                <div class="d-flex justify-space-between">
                    <div class="d-flex py-0 align-center">
                        <div>
                            <h3 class="text-h5 card-title">รายงานยอดเซ็นสัญญา (มูลค่า x ภูมิภาค)</h3>
                            <ul
                                class="v-breadcrumbs v-breadcrumbs--density-default text-subtitle-1 textSecondary pa-0 ml-n1">
                                <li class="v-breadcrumbs-item" text="Home">
                                    <a class="v-breadcrumbs-item--link" href="#">
                                        <p>หน้าแรก</p>
                                    </a>
                                </li>
                                <li class="v-breadcrumbs-divider">
                                    <i class="mdi-chevron-right mdi v-icon notranslate v-theme--BLUE_THEME"
                                        aria-hidden="true" style="font-size: 15px; height: 15px; width: 15px"></i>
                                </li>
                                <li class="v-breadcrumbs-item" text="Dashboard">
                                    <a class="v-breadcrumbs-item--link" href="#">
                                        <p>รายงานยอดเซ็นสัญญา (มูลค่า x ภูมิภาค)</p>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </v-col>



        <v-col cols="12" sm="12" lg="12">
            <v-card elevation="10">
                <v-card-text>
                    <v-row>
                        <v-col cols="12" sm="4" md="4">
                            <v-select v-model="selectedYear" :items="yearOptions" label="ปี (พ.ศ.)" density="compact"
                                variant="outlined" hide-details />
                        </v-col>
                        <v-col cols="12" sm="4" md="4">
                            <v-select v-model="selectedQuarter" :items="quarterOptions" item-title="title"
                                item-value="value" label="ไตรมาส" density="compact" variant="outlined" hide-details />
                        </v-col>
                        <v-col cols="12" sm="4" md="4">
                            <v-select v-model="selectedMonths" :items="monthOptions" item-title="title"
                                item-value="value" label="เดือน (เลือกได้หลายเดือน)" multiple chips closable-chips
                                density="compact" variant="outlined" hide-details />
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>
        </v-col>

        <v-col cols="12" sm="12" lg="12">
            <div class="v-row">
                <div v-for="field in dataTypes" :key="field" class="v-col-sm-6 v-col-lg-3 v-col-12 py-0 mb-0">
                    <div class="v-card v-theme--BLUE_THEME v-card--density-default elevation-10 rounded-md v-card--variant-elevated"
                        @click="highlightRow(field)" style="cursor: pointer;" hover
                        :class="{ 'card-is-active': selectedHighlight === field }">
                        <div class="v-card-text pa-5">
                            <div class="d-flex align-center ga-4">
                                <button type="button"
                                    class="v-btn v-btn--elevated v-btn--icon v-theme--BLUE_THEME v-btn--density-default v-btn--size-default v-btn--variant-elevated"
                                    :class="{
                                        'bg-primary': field === 'unit',
                                        'bg-secondary': field === 'value',
                                        'bg-error': field === 'area',
                                        'bg-warning': field === 'price_per_sqm'
                                    }" dark>
                                    <svg v-if="field === 'unit'" xmlns="http://www.w3.org/2000/svg" width="24"
                                        height="24" viewBox="0 0 24 24">
                                        <g fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path
                                                d="M2 12.204c0-2.289 0-3.433.52-4.381c.518-.949 1.467-1.537 3.364-2.715l2-1.241C9.889 2.622 10.892 2 12 2s2.11.622 4.116 1.867l2 1.241c1.897 1.178 2.846 1.766 3.365 2.715S22 9.915 22 12.203v1.522c0 3.9 0 5.851-1.172 7.063S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.212S2 17.626 2 13.725z" />
                                            <path stroke-linecap="round" d="M12 15v3" />
                                        </g>
                                    </svg>
                                    <svg v-else-if="field === 'value'" xmlns="http://www.w3.org/2000/svg" width="24"
                                        height="24" viewBox="0 0 24 24">
                                        <g fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path
                                                d="M2 14c0-3.771 0-5.657 1.172-6.828S6.229 6 10 6h4c3.771 0 5.657 0 6.828 1.172S22 10.229 22 14s0 5.657-1.172 6.828S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.172S2 17.771 2 14Zm14-8c0-1.886 0-2.828-.586-3.414S13.886 2 12 2s-2.828 0-3.414.586S8 4.114 8 6" />
                                            <path stroke-linecap="round"
                                                d="M12 17.333c1.105 0 2-.746 2-1.666S13.105 14 12 14s-2-.746-2-1.667c0-.92.895-1.666 2-1.666m0 6.666c-1.105 0-2-.746-2-1.666m2 1.666V18m0-8v.667m0 0c1.105 0 2 .746 2 1.666" />
                                        </g>
                                    </svg>
                                    <svg v-else-if="field === 'area'" xmlns="http://www.w3.org/2000/svg" width="24"
                                        height="24" viewBox="0 0 24 24">
                                        <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5">
                                            <path
                                                d="M11 2c-4.055.007-6.178.107-7.536 1.464C2 4.928 2 7.285 2 11.999s0 7.071 1.464 8.536C4.93 21.999 7.286 21.999 12 21.999s7.071 0 8.535-1.464c1.358-1.357 1.457-3.48 1.464-7.536" />
                                            <path stroke-linejoin="round"
                                                d="m13 11l9-9m0 0h-5.344M22 2v5.344M21 3l-9 9m0 0h4m-4 0V8" />
                                        </g>
                                    </svg>
                                    <svg v-else-if="field === 'price_per_sqm'" xmlns="http://www.w3.org/2000/svg"
                                        width="24" height="24" viewBox="0 0 24 24">
                                        <g fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path
                                                d="M4.979 9.685C2.993 8.891 2 8.494 2 8s.993-.89 2.979-1.685l2.808-1.123C9.773 4.397 10.767 4 12 4s2.227.397 4.213 1.192l2.808-1.123C21.007 7.109 22 7.506 22 8s-.993-.89-2.979 1.685l-2.808 1.124C14.227 11.603 13.233 12 12 12s-2.227-.397-4.213-1.191z" />
                                            <path
                                                d="m5.766 10l-.787.315C2.993 11.109 2 11.507 2 12s.993.89 2.979 1.685l2.808 1.124C9.773 15.603 10.767 16 12 16s2.227-.397 4.213-1.191l2.808-1.124C21.007 12.891 22 12.493 22 12s-.993-.89-2.979-1.685L18.234 10" />
                                            <path
                                                d="m5.766 14l-.787.315C2.993 15.109 2 15.507 2 16s.993.89 2.979 1.685l2.808 1.124C9.773 19.603 10.767 20 12 20s2.227-.397 4.213-1.192l2.808-1.123C21.007 16.891 22 16.494 22 16c0-.493-.993-.89-2.979-1.685L18.234 14" />
                                        </g>
                                    </svg>
                                </button>
                                <div class="">
                                    <div class="d-flex align-end ga-2">
                                        <h2 class="text-h4" style="line-height: 1.1;">
                                            {{ formatValue(summaryCardData[field], field) }}
                                        </h2>
                                    </div>

                                    <span v-if="summaryCardYTDData[field]" class="mom-card" :class="{
                                        'text-success': summaryCardYTDData[field] && summaryCardYTDData[field].includes('+'),
                                        'text-error': summaryCardYTDData[field] && summaryCardYTDData[field].includes('-')
                                    }" style="font-size: 9px; font-weight: 400; line-height: 1.2; display: block;">
                                        {{ summaryCardYTDData[field] }}
                                    </span>

                                    <p class="textSecondary text-15" style="line-height: 1.2;">
                                        {{ dataTypeLabels[field] }} (รวม)
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </v-col>

        <v-col cols="12">
            <VCard elevation="10">
                <v-card-text>
                    <div class="v-row ">
                        <div class="v-col-md-8 v-col-12">
                            <div class="d-flex align-center">
                                <div>
                                    <h3 class="card-title mb-1">รายงานยอดเซ็นสัญญา {{ filterSubtitle }}</h3>
                                    <h5 class="card-subtitle" style="text-align: left">{{ dynamicUnitSubtitle }}</h5>
                                </div>
                            </div>
                        </div>

                        <div class="v-col-md-4 v-col-12">
                            <v-row v-if="chartMoMData.hasPreviousMonth" align="center" justify="end" class="mt-0">
                                <v-col cols="auto" class="text-right">
                                    <h3 class="card-title" :class="getPercentageColor(chartMoMData.percent)"
                                        style="font-size: 1.25rem;">
                                        {{ formatPercentage(chartMoMData.percent) }}
                                    </h3>
                                    <h5 class="card-subtitle text-grey-darken-1"> {{ chartMoMData.label }} </h5>
                                </v-col>
                            </v-row>
                            <div v-else-if="chartMoMData.label" class="text-right text-grey mt-2 pa-2">
                                <h5 class="card-subtitle text-grey-darken-1">{{ chartMoMData.label }}</h5>
                            </div>
                        </div>
                    </div>

                    <v-row class="mt-0">
                        <v-col cols="12" md="12">
                            <div class="d-flex justify-space-between align-center flex-wrap ga-2">
                                <v-switch v-model="showMoM" label="แสดง MoM%" color="primary" density="compact"
                                    hide-details class="flex-shrink-0"></v-switch>

                                <v-btn-toggle v-model="selectedTimeRange" variant="outlined" density="compact"
                                    color="primary" class="flex-wrap">
                                    <v-btn v-for="range in timeRangeOptions" :key="range.value" :value="range.value"
                                        size="small">
                                        {{ range.label }}
                                    </v-btn>
                                </v-btn-toggle>
                            </div>
                        </v-col>
                    </v-row>

                    <div class="mt-5">
                        <apexchart type="polarArea" :options="polarAreaOptions" :series="polarAreaSeries"
                            height="400" />
                    </div>
                </v-card-text>
            </VCard>
        </v-col>

        <v-col cols="12" sm="12" lg="12">
            <v-card elevation="10" style="position: relative;"> <v-overlay :model-value="exportLoading"
                    class="align-center justify-center " persistent scrim="white" style="opacity: 0.8;">
                    <div class="text-center">
                        <v-progress-circular color="primary" indeterminate size="64"></v-progress-circular>
                        <h4 class="text-primary mt-3">กำลังสร้างไฟล์รายงาน...</h4>
                    </div>
                </v-overlay><br></br>

                <v-card-title class="d-flex justify-space-between align-center flex-wrap ">
                    <div>
                        <h3 class="card-title mb-1">ตารางยอดเซ็นสัญญา {{ filterSubtitle }}</h3>
                        <h5 class="card-subtitle" style="text-align: left">{{ dynamicUnitSubtitle }}</h5>
                    </div>
                    <div class="d-flex justify-end ga-2 mt-2">
                        <v-btn color="primary" variant="outlined" @click="exportToExcel" class="v-btn--size-small"
                            :loading="exportLoading">
                            <v-icon start>mdi-file-excel</v-icon>
                            Export Excel
                        </v-btn>

                        <v-btn color="primary" variant="outlined" @click="exportToPdf" class="v-btn--size-small"
                            :loading="exportLoading">
                            <v-icon start>mdi-file-pdf-box</v-icon>
                            Export PDF
                        </v-btn>
                    </div>
                </v-card-title>

                <v-card-text>
                    <div class="v-table v-theme--BLUE_THEME v-table--density-default month-table">
                        <div class="v-table__wrapper" style="overflow-x: auto">
                            <table id="region-table-to-export">
                                <thead style="background-color: #f5f5f5">
                                    <tr>
                                        <th class="text-h6" style="min-width: 80px; text-align: left;"></th>
                                        <th v-for="month_item in displayedMonths" :key="month_item.value" class="text-p"
                                            style="font-size: 13px; text-align: center">
                                            {{ month_item.short }}
                                        </th>
                                        <th class="text-p"
                                            style="font-size: 13px; font-weight: 600; background-color: #fff3e0; text-align: center">
                                            รวม
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <template v-for="region in regions" :key="region">
                                        <tr class="month-item" style="background-color: #fcf8ff">
                                            <td style="text-align: left;">
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 600; color: #725af2">{{ region
                                                    }}</h6>
                                            </td>
                                            <td :colspan="displayedMonths.length + 1"></td>
                                        </tr>

                                        <tr class="month-item" v-for="field in dataTypes" :key="region + field"
                                            v-show="isRowVisible(field)" :style="getHighlightStyle(field)">
                                            <td style="text-align: left;">
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 400; padding-left: 15px">
                                                    {{ dataTypeLabels[field] }}
                                                </h6>
                                            </td>

                                            <td v-for="month_item in displayedMonths"
                                                :key="region + field + month_item.value"
                                                style="text-align: right; vertical-align: top;">
                                                <div>
                                                    <h6 class="text-p" style="font-size: 12px; font-weight: 400;">
                                                        {{ formatValue(getMonthlyVerticalTotal(month_item.value, region,
                                                        field), field) }}
                                                    </h6>
                                                    <span v-if="showMoM" style="font-size: 9px !important;"
                                                        v-html="getMoMFormatted_VerticalTotal(month_item.value, region, field)"></span>
                                                </div>
                                            </td>

                                            <td style="background-color: #fff3e0; text-align: right; vertical-align: top;"
                                                :style="getHighlightStyle(field)">
                                                <div>
                                                    <h6 class="text-p" style="font-size: 12px; font-weight: 600">
                                                        {{ formatValue(getRegionHorizontalTotal(region, field), field)
                                                        }}
                                                    </h6>
                                                    <span v-if="showMoM" style="font-size: 9px !important;"
                                                        v-html="formatYoYSpan(getRegionHorizontalTotalYoY(region, field))">
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>

                                    <tr class="month-item" style="background-color: #fcf8ff;" v-for="field in dataTypes"
                                        :key="'total-' + field" v-show="isRowVisible(field)"
                                        :style="getHighlightStyle(field)">
                                        <td style="text-align: left;">
                                            <h6 class="text-p"
                                                style="font-size: 13px; font-weight: 600; color: #f8285a">
                                                {{ dataTypeLabels[field] }} (รวม)
                                            </h6>
                                        </td>

                                        <td v-for="month_item in displayedMonths"
                                            :key="'total-' + month_item.value + field"
                                            style="text-align: right; vertical-align: top;">
                                            <div>
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 600; color: #f8285a">
                                                    {{ formatValue(getMonthlyGrandTotal(month_item.value, field), field)
                                                    }}
                                                </h6>
                                                <span v-if="showMoM" style="font-size: 9px !important;"
                                                    v-html="getMoMFormatted_GrandTotal(month_item.value, field)"></span>
                                            </div>
                                        </td>

                                        <td style="background-color: #fff3e0; text-align: right; vertical-align: top;"
                                            :style="getHighlightStyle(field)">
                                            <div>
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 800; color: #f8285a">
                                                    {{ formatValue(getMonthlyTabGrandTotal(field), field) }}
                                                </h6>
                                                <span v-if="showMoM"
                                                    style="font-size: 9px !important; font-weight: 600;"
                                                    v-html="formatYoYSpan(getSummaryGrandTotalYoY(field))">
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </v-card-text>
            </v-card>
        </v-col>

    </v-row>
</template>

<style scoped>
.text-subtitle-1 {
    font-size: 14px;
}

.text-h6 {
    font-size: 18px;
}

th.text-left,
td.text-left {
    text-align: left !important;
    padding-left: 16px !important;
}

th.text-right,
td.text-right {
    text-align: right !important;
    padding-right: 16px !important;
}

.month-item td,
.month-item th {
    padding: 8px !important;
    border-bottom: 1px solid #eee;
}

:deep(.v-slide-group__container) {
    background-color: #FFFFFF;
    border-radius: 5px;
}

:deep(.v-tab) {
    background-color: #FFFFFF;
    color: #000000 !important;
    opacity: 0.8;
    border-radius: 5px;
}

:deep(.v-tab:hover) {
    background-color: #2196F3 !important;
    color: #FFFFFF !important;
    opacity: 1;
    border-radius: 5px;
}

:deep(.v-tab--selected) {
    background-color: #2196F3 !important;
    color: #FFFFFF !important;
    opacity: 1;
    border-radius: 5px;
}

.mom-percent {
    font-size: 5px;
    margin-left: 4px;
    white-space: nowrap;
}

.text-success {
    color: #28a745;
}

.text-error {
    color: #ed0b22;
}

.mom-card {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 5px;
    white-space: nowrap;
}

.month-item td {
    vertical-align: middle;
}

/* ⭐️ [ใหม่] CSS สำหรับการ์ดที่คลิกได้ (เหมือน house.vue) ⭐️ */
.v-card[style*="cursor: pointer"] {
    transition: transform 0.2s ease-in-out, background-color 0.2s ease-in-out;
}

.v-card[style*="cursor: pointer"]:hover,
.v-card.card-is-active {
    background-color: #E3F2FD !important;
    transform: translateY(-2px);
}

.v-card[style*="cursor: pointer"]:hover .text-h4,
.v-card[style*="cursor: pointer"]:hover .textSecondary,
.v-card.card-is-active .text-h4,
.v-card.card-is-active .textSecondary {
    color: #1E88E5 !important;
}

/* ⭐️ [ใหม่] CSS สำหรับปุ่ม TimeRange (จาก house.vue) ⭐️ */
.flex-wrap {
    flex-wrap: wrap;
    height: auto !important;
}
</style>