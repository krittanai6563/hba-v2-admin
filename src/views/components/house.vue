<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import * as XLSX from 'xlsx';
import ExcelJS from 'exceljs';
import jsPDF from 'jspdf';
import html2canvas from 'html2canvas'; // (!!!) 1. เพิ่ม Import

import type { BorderStyle, Cell } from 'exceljs';

// --- (ส่วนฟิลเตอร์เหมือนเดิม) ---
const jsDate = new Date();
const currentJsYear = jsDate.getFullYear();
const currentJsMonth = jsDate.getMonth() + 1; // (1-12)

const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user';

// (!!!) 2. เพิ่ม Refs สำหรับ Loading และ Snackbar
const exportLoading = ref(false);
const showSnackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('info');
const snackbarTimeout = ref(5000);

// (1) โครงสร้างข้อมูลสำหรับ กราฟ, การ์ดรวม (เหมือนเดิม)
interface SummaryData {
    unit: Record<string, number>;
    total_value: Record<string, number>;
    usable_area: Record<string, number>;
    price_per_sqm: Record<string, number>;
}
const summaryData = ref<SummaryData>({
    unit: {},
    total_value: {},
    usable_area: {},
    price_per_sqm: {}
});

// (2) โครงสร้างข้อมูลสำหรับ "ตาราง" ใหม่ (เหมือน V3)
interface PriceRangeMetrics {
    unit: number;
    total_value: number;
    usable_area: number;
    price_per_sqm: number;
}
// key1: month (number), key2: priceRange (string)
const detailedTableData = ref<Record<number, Record<string, PriceRangeMetrics>>>({});
// ✅ [เพิ่มใหม่] State สำหรับเก็บข้อมูลปีก่อน (สำหรับเทียบ YoY)
const previousYearDetailedTableData = ref<Record<number, Record<string, PriceRangeMetrics>>>({});


const priceRanges = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป'];

const dataTypes: (keyof PriceRangeMetrics)[] = ['unit', 'total_value', 'usable_area', 'price_per_sqm'];
// (Label สำหรับแถวในตาราง)
const dataTypeLabels: Record<string, string> = {
    unit: 'จำนวนหลัง',
    total_value: 'มูลค่ารวม',
    usable_area: 'พื้นที่ใช้สอย',
    price_per_sqm: 'ราคาเฉลี่ย/ตร.ม.'
};


const allMonthItems = [
    { title: 'มกราคม', short: 'ม.ค.', value: 1 },
    { title: 'กุมภาพันธ์', short: 'ก.พ.', value: 2 },
    { title: 'มีนาคม', short: 'มี.ค.', value: 3 },
    { title: 'เมษายน', short: 'เม.ย.', value: 4 },
    { title: 'พฤษภาคม', short: 'พ.ค.', value: 5 },
    { title: 'มิถุนายน', short: 'มิ.ย.', value: 6 },
    { title: 'กรกฎาคม', short: 'ก.ค.', value: 7 },
    { title: 'สิงหาคม', short: 'ส.ค.', value: 8 },
    { title: 'กันยายน', short: 'ก.ย.', value: 9 },
    { title: 'ตุลาคม', short: 'ต.ค.', value: 10 },
    { title: 'พฤศจิกายน', short: 'พ.ย.', value: 11 },
    { title: 'ธันวาคม', short: 'ธ.ค.', value: 12 }
];

const selectedYear = ref(currentJsYear + 543);
const isUpdatingFromMonths = ref(false);
const selectedQuarter = ref('all');
const selectedMonths = ref<number[]>([]);
const yearOptions = ref(
    Array.from({ length: 5 }, (_, i) => currentJsYear + 543 - i)
);


const monthOptions = computed(() => {
    const yearAD = selectedYear.value - 543;
    if (yearAD === currentJsYear) {
        // ⭐️ ถ้าเป็นปีปัจจุบัน ให้แสดงเฉพาะเดือนที่ผ่านมาแล้ว
        return allMonthItems.filter(m => m.value <= currentJsMonth);
    } else if (yearAD > currentJsYear) {
        // ⭐️ ถ้าเป็นปีอนาคต ไม่ต้องแสดงเดือนเลย
        return [];
    } else {
        // ⭐️ ถ้าเป็นปีที่ผ่านมา ให้แสดงทั้ง 12 เดือน
        return allMonthItems;
    }
});

const quarterOptions = ref([
    { title: 'ทุกไตรมาส / ทุกเดือน', value: 'all' },
    { title: 'ไตรมาส 1 (ม.ค. - มี.ค.)', value: 'Q1' },
    { title: 'ไตรมาส 2 (เม.ย. - มิ.ย.)', value: 'Q2' },
    { title: 'ไตรมาส 3 (ก.ค. - ก.ย.)', value: 'Q3' },
    { title: 'ไตรมาส 4 (ต.ค. - ธ.ค.)', value: 'Q4' }
]);

// (4.1) ♻️ [แก้ไข] เปลี่ยน Options เป็น computed เพื่อให้ Tooltip และ DataLabel เปลี่ยนตาม
const computedPolarAreaOptions = computed(() => {

    // 1. ตรวจสอบ Metric, คำต่อท้าย (Suffix) และ Title
    let selectedMetricKey: keyof SummaryData = 'total_value';
    let tooltipSuffix = ' บาท';
    let dataLabelTitle = 'มูลค่ารวม';

    if (selectedHighlight.value === 'จำนวนหลัง') {
        selectedMetricKey = 'unit';
        tooltipSuffix = ' หลัง';
        dataLabelTitle = 'จำนวนหลัง';
    } else if (selectedHighlight.value === 'พื้นที่ใช้สอย') {
        selectedMetricKey = 'usable_area';
        tooltipSuffix = ' ตร.ม.';
        dataLabelTitle = 'พื้นที่ใช้สอย';
    } else if (selectedHighlight.value === 'ราคาเฉลี่ย/ตร.ม.') {
        selectedMetricKey = 'price_per_sqm';
        tooltipSuffix = ' บาท/ตร.ม.';
        dataLabelTitle = 'ราคาเฉลี่ย/ตร.ม.';
    }

    // 2. คืนค่า Options Object ใหม่
    return {
        chart: { type: 'polarArea', fontFamily: 'inherit', foreColor: '#6c757d' },
        labels: priceRanges,
        legend: { position: 'bottom', horizontalAlign: 'center' },
        stroke: { colors: ['#fff'] },
        fill: { opacity: 0.8 },
        responsive: [{ breakpoint: 480, options: { chart: { width: 200 }, legend: { position: 'bottom' } } }],

        // --- (Dynamic Tooltip) ---
        tooltip: {
            theme: 'dark',
            y: {
                // ♻️ [แก้ไข] เพิ่มทศนิยม 2 ตำแหน่ง
                formatter: (val: number) => val.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + tooltipSuffix
            }
        },

        // ... (อยู่ภายใน computedPolarAreaOptions)

        dataLabels: {
            enabled: true,

            // ⭐️ [แทนที่] formatter เดิมทั้งหมด
            formatter: (val: number, opts: any) => {
                let percentageText = '0.00%';
                if (!isNaN(val)) percentageText = (Number(val) || 0).toFixed(2) + '%';

                // ⭐️ (ใช้ selectedMetricKey จาก scope ด้านบน)
                if (!summaryData.value || !summaryData.value[selectedMetricKey]) return percentageText;

                // ⭐️ [แก้ไข] ⭐️
                // เปลี่ยนจาก dataPointIndex (ที่ส่ง undefined) เป็น seriesIndex
                const index = opts.seriesIndex;
                const rangeKey = priceRanges[index]; // ⭐️ ใช้ index ใหม่

                if (!rangeKey) {
                    console.warn("ไม่สามารถหา rangeKey ได้, index:", index);
                    return percentageText;
                }

                // @ts-ignore
                const rawValue = summaryData.value[selectedMetricKey][rangeKey];

                if (rawValue === undefined || rawValue === null) return percentageText;

                // 1. จัดรูปแบบ "ค่าจริง" (ไม่ใส่วงเล็บ)
                const rawValueText = (Number(rawValue) || 0).toLocaleString('th-TH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                // 2. [แก้ไข] สลับลำดับ และใส่วงเล็บให้ %
                //    (เอาค่าจริงขึ้นก่อน ตามด้วยเปอร์เซ็นต์)
                return [rawValueText, `(${percentageText})`];
            },

            // ⭐️ (ตรวจสอบว่า style และ dropShadow เป็นแบบนี้)
            style: {
                fontSize: '10px'
                // ⭐️ (ห้ามมี colors: [...] เพราะจะทำให้ Array formatter ไม่ทำงาน)
            },
            dropShadow: { enabled: false }
        },

        noData: { text: 'ไม่พบข้อมูลสำหรับช่วงที่เลือก', align: 'center', verticalAlign: 'middle', offsetY: 0, style: { color: '#6c757d', fontSize: '14px', fontFamily: 'inherit' } },
    };
});

// (4.2) ✅ [เพิ่มใหม่] Computed สำหรับหัวข้อกราฟ
const chartTitle = computed(() => {
    const selected = selectedHighlight.value;
    if (selected === 'จำนวนหลัง') return 'สัดส่วนจำนวนหลัง (Unit) ตามช่วงราคา';
    if (selected === 'พื้นที่ใช้สอย') return 'สัดส่วนพื้นที่ใช้สอย (Usable Area) ตามช่วงราคา';
    if (selected === 'ราคาเฉลี่ย/ตร.ม.') return 'สัดส่วนราคาเฉลี่ย/ตร.ม. (Price/Sqm) ตามช่วงราคา';
    return 'สัดส่วนมูลค่ารวม (Total Value) ตามมูลค่า'; // 
});


const fetchSummary = async () => {
    if (!userId && userRole !== 'admin') return;
    if (selectedMonths.value.length === 0 || !selectedYear.value) {
        summaryData.value = { unit: {}, total_value: {}, usable_area: {}, price_per_sqm: {} };
        return;
    }
    try {
        const res = await fetch('https://uat.hba-sales.org/backend/get_contract_summary_main.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                user_id: userId,
                buddhist_year: selectedYear.value,
                months: selectedMonths.value.sort((a, b) => a - b),
                role: userRole
            })
        });
        if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
        const data = await res.json();
        const aggregatedData: SummaryData = {
            unit: { total: 0 }, total_value: { total: 0 }, usable_area: { total: 0 }, price_per_sqm: { total: 0 }
        };
        for (const range of priceRanges) {
            aggregatedData.unit[range] = 0; aggregatedData.total_value[range] = 0; aggregatedData.usable_area[range] = 0; aggregatedData.price_per_sqm[range] = 0;
        }
        let totalAreaForAvg = 0; let totalValueForAvg = 0;
        const regions = Object.keys(data);
        for (const region of regions) {
            for (const range of priceRanges) {
                if (data[region] && data[region][range]) {
                    const metrics = data[region][range];
                    const unitValue = Number(metrics.unit) || 0;
                    const valueValue = Number(metrics.value) || 0;
                    const areaValue = Number(metrics.area) || 0;

                    aggregatedData.unit[range] += unitValue;
                    aggregatedData.total_value[range] += valueValue;
                    aggregatedData.usable_area[range] += areaValue;
                    aggregatedData.unit['total'] += unitValue;
                    aggregatedData.total_value['total'] += valueValue;
                    aggregatedData.usable_area['total'] += areaValue;

                    totalValueForAvg += valueValue;
                    totalAreaForAvg += areaValue;
                }
            }
        }
        for (const range of priceRanges) {
            if (aggregatedData.usable_area[range] > 0) {
                // ♻️ [แก้ไข] เปลี่ยนจาก Math.round เป็นการหารปกติ
                aggregatedData.price_per_sqm[range] = aggregatedData.total_value[range] / aggregatedData.usable_area[range];
            } else { aggregatedData.price_per_sqm[range] = 0; }
        }
        if (totalAreaForAvg > 0) {
            // ♻️ [แก้ไข] เปลี่ยนจาก Math.round เป็นการหารปกติ
            aggregatedData.price_per_sqm['total'] = totalValueForAvg / totalAreaForAvg;
        } else { aggregatedData.price_per_sqm['total'] = 0; }

        summaryData.value = aggregatedData;
        console.log('✅ ข้อมูลที่ประมวลผลแล้ว (สำหรับกราฟ/การ์ด):', aggregatedData);
    } catch (err) { console.error('❌ Error fetching summary:', err); }
};

// ✅ [เพิ่มใหม่] Helper function (ย้ายมาจากใน fetchDetailed) เพื่อประมวลผลข้อมูลดิบ
// ♻️ [แก้ไข] ปรับการคำนวณ price_per_sqm ให้เก็บทศนิยม
function processMonthData(data: any): Record<string, PriceRangeMetrics> {
    const monthData: Record<string, PriceRangeMetrics> = {};

    // เริ่มต้นค่าให้ครบทุกช่วงราคา
    for (const range of priceRanges) {
        monthData[range] = { unit: 0, total_value: 0, usable_area: 0, price_per_sqm: 0 };
    }

    if (!data) return monthData; // ถ้า API คืนค่า null (เช่น error)

    const regions = Object.keys(data);
    for (const region of regions) {
        for (const range of priceRanges) {
            if (data[region] && data[region][range]) {
                const metrics = data[region][range];
                monthData[range].unit += (Number(metrics.unit) || 0);
                monthData[range].total_value += (Number(metrics.value) || 0);
                monthData[range].usable_area += (Number(metrics.area) || 0);
            }
        }
    }

    // คำนวณ price_per_sqm
    for (const range of priceRanges) {
        const rangeData = monthData[range];
        if (rangeData.usable_area > 0) {
            // ♻️ [แก้ไข] เปลี่ยนจาก Math.round เป็นการหารปกติ เพื่อเก็บทศนิยม
            rangeData.price_per_sqm = rangeData.total_value / rangeData.usable_area;
        } else {
            rangeData.price_per_sqm = 0;
        }
    }
    return monthData;
}



// (6) ♻️ [แก้ไข] ฟังก์ชันสำหรับดึงข้อมูลตาราง "แบบละเอียด" (ปรับปรุงใหม่ทั้งหมด)
const fetchDetailedTableData = async () => {
    if (!userId && userRole !== 'admin') return;

    let monthsToFetch = [...selectedMonths.value].sort((a, b) => a - b);

    if (monthsToFetch.length === 0 || !selectedYear.value) {
        detailedTableData.value = {};
        previousYearDetailedTableData.value = {}; // ✅ [เพิ่มใหม่] ล้างข้อมูลปีก่อน
        return;
    }

    // ✅ [เพิ่มใหม่] ตรวจสอบและเพิ่ม "เดือนก่อนหน้า" 1 เดือนสำหรับคำนวณ MoM
    const firstMonth = monthsToFetch[0];
    if (firstMonth > 1) {
        const prevMonth = firstMonth - 1;
        if (!monthsToFetch.includes(prevMonth)) {
            monthsToFetch.push(prevMonth); // เพิ่มเดือนก่อนหน้าเข้าไปใน list ที่จะ fetch
        }
    }

    const currentYear = selectedYear.value;
    const previousYear = currentYear - 1;

    // ✅ [เพิ่มใหม่] สร้างฟังก์ชันย่อยสำหรับ Fetch API
    const fetchMonthData = async (year: number, month: number) => {
        try {
            const res = await fetch('https://uat.hba-sales.org/backend/get_contract_summary_main.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    user_id: userId,
                    buddhist_year: year,
                    months: [month],
                    role: userRole
                })
            });
            if (!res.ok) {
                console.error(`Error fetching data for month ${month}/${year}: Status ${res.status}`);
                return null; // คืนค่า null ถ้า error
            }
            return await res.json();
        } catch (err) {
            console.error(`❌ Error fetching detailed summary for month ${month}/${year}:`, err);
            return null; // คืนค่า null ถ้า error
        }
    };

    // ✅ [เพิ่มใหม่] สร้างรายการ Promises ทั้งหมด (ปีปัจจุบัน + ปีก่อน)
    const promises: Promise<{ month: number; year: number; data: any; }>[] = [];

    for (const month of monthsToFetch) {
        // ข้อมูลปีปัจจุบัน
        promises.push(fetchMonthData(currentYear, month).then(data => ({ month, year: currentYear, data })));
        // ข้อมูลปีก่อน
        promises.push(fetchMonthData(previousYear, month).then(data => ({ month, year: previousYear, data })));
    }

    // ✅ [เพิ่มใหม่] สั่ง Run Promise ทั้งหมดพร้อมกัน
    const results = await Promise.all(promises);

    const newCurrentYearData: Record<number, Record<string, PriceRangeMetrics>> = {};
    const newPreviousYearData: Record<number, Record<string, PriceRangeMetrics>> = {};

    // ✅ [เพิ่มใหม่] ประมวลผลข้อมูลที่ได้กลับมา
    for (const result of results) {
        if (result.year === currentYear && result.data) {
            newCurrentYearData[result.month] = processMonthData(result.data);
        } else if (result.year === previousYear && result.data) {
            newPreviousYearData[result.month] = processMonthData(result.data);
        }
    }

    detailedTableData.value = newCurrentYearData;
    previousYearDetailedTableData.value = newPreviousYearData; // ✅ [เพิ่มใหม่] เก็บข้อมูลปีก่อน

    console.log('✅ ข้อมูลตารางแบบละเอียด (ปัจจุบัน):', newCurrentYearData);
    console.log('✅ ข้อมูลตารางแบบละเอียด (ปีก่อน):', newPreviousYearData);
};

// (7) ฟังก์ชัน helper สำหรับตารางแบบละเอียด
// ♻️ [แก้ไข] ปรับการแสดงผลทศนิยม
const getDetailedValue = (type: keyof PriceRangeMetrics, monthValue: number, range: string) => {
    const value = detailedTableData.value?.[monthValue]?.[range]?.[type] || 0;

    if (type === 'unit') {
        // จำนวนหลังไม่มีทศนิยม
        return value.toLocaleString('th-TH', { maximumFractionDigits: 0 });
    }
    // ที่เหลือ (value, area, price_per_sqm) ให้มี 2 ตำแหน่ง
    return value.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};


// ----------------------------------------------------------------
// ✅ [เพิ่มใหม่] (8) State และ Logic สำหรับฟิลเตอร์ปุ่ม
// ----------------------------------------------------------------
const showMoM = ref(true); // State สำหรับการสลับเปิด/ปิด MoM%
const selectedTimeRange = ref<string | null>(null); // State สำหรับ 1M, 2M, 3M, 6M, YTD
const timeRangeOptions = [
    { label: '1M', value: '1M' },
    { label: '2M', value: '2M' },
    { label: '3M', value: '3M' },
    { label: '6M', value: '6M' },
    { label: 'YTD', value: 'YTD' }
];

// Helper function: ดึงเดือนที่ใช้งานได้ของปีที่เลือก
function getAvailableMonths(yearInBE: number): number[] {
    const yearAD = yearInBE - 543;
    const allMonths = allMonthItems.map(m => m.value);
    if (yearAD === currentJsYear) {
        return allMonths.filter(m => m <= currentJsMonth);
    } else if (yearAD > currentJsYear) {
        return [];
    } else {
        return allMonths;
    }
}

// Function: ตั้งค่า `selectedMonths` ตามปุ่มที่เลือก (1M, 2M, ...)
function setTimeRange(range: string | null) {
    if (!range) {
        return;
    }
    const availableMonths = getAvailableMonths(selectedYear.value);
    if (availableMonths.length === 0) {
        selectedMonths.value = [];
        return;
    }

    let newSelectedMonths: number[] = [];
    switch (range) {
        case '1M': newSelectedMonths = availableMonths.slice(-1); break;
        case '2M': newSelectedMonths = availableMonths.slice(-2); break;
        case '3M': newSelectedMonths = availableMonths.slice(-3); break;
        case '6M': newSelectedMonths = availableMonths.slice(-6); break;
        case 'YTD': newSelectedMonths = [...availableMonths]; break;
    }

    selectedMonths.value = newSelectedMonths;
}

// Watcher: เมื่อปุ่ม (v-btn-toggle) ถูกคลิก
watch(selectedTimeRange, (newRange) => {
    if (newRange) {
        setTimeRange(newRange); // เรียกใช้ setTimeRange เพื่ออัปเดต `selectedMonths`
    }
    // ถ้า newRange เป็น null (เช่น ผู้ใช้เลือกเดือนเอง) 
    // `watch(selectedMonths)` จะจัดการ state นี้
});
// ----------------------------------------------------------------


watch(selectedQuarter, (newQuarter) => {
    if (isUpdatingFromMonths.value) {
        isUpdatingFromMonths.value = false;
        return;
    }
    if (newQuarter === 'all') updateToAllMonths();
    else if (newQuarter === 'Q1') selectedMonths.value = [1, 2, 3];
    else if (newQuarter === 'Q2') selectedMonths.value = [4, 5, 6];
    else if (newQuarter === 'Q3') selectedMonths.value = [7, 8, 9];
    else if (newQuarter === 'Q4') selectedMonths.value = [10, 11, 12];
});

watch(selectedYear, () => {
    // ♻️ [แก้ไข] ตรวจสอบว่ามี time range ค้างอยู่หรือไม่
    if (selectedTimeRange.value) {
        // ถ้ามี ให้คำนวณ `selectedMonths` ใหม่สำหรับปีใหม่
        setTimeRange(selectedTimeRange.value);
    } else {
        // ถ้าไม่มี (เลือกแบบ manual หรือ Q) ให้ใช้ logic เดิม
        const validMonths = monthOptions.value.map(m => m.value);
        selectedMonths.value = selectedMonths.value.filter(m => validMonths.includes(m));
        if (selectedQuarter.value === 'all') {
            updateToAllMonths();
        } else {
            // `selectedMonths` อาจจะเปลี่ยน (หรืออาจจะไม่) แต่เราต้อง fetch ใหม่สำหรับปีใหม่
            fetchSummary();
            fetchDetailedTableData();
        }
    }
});

watch(selectedMonths, () => {
    const sortedMonths = [...selectedMonths.value].sort((a, b) => a - b).join(',');

    // --- (1) อัปเดต ไตรมาส ---
    if (sortedMonths === '1,2,3') selectedQuarter.value = 'Q1';
    else if (sortedMonths === '4,5,6') selectedQuarter.value = 'Q2';
    else if (sortedMonths === '7,8,9') selectedQuarter.value = 'Q3';
    else if (sortedMonths === '10,11,12') selectedQuarter.value = 'Q4';
    else {
        const allMonthsCurrentYear = allMonthItems.map(m => m.value).slice(0, currentJsMonth).join(',');
        const allMonthsPastYear = allMonthItems.map(m => m.value).join(',');
        if (sortedMonths === allMonthsCurrentYear || sortedMonths === allMonthsPastYear) {
            if (selectedQuarter.value !== 'all') {
                isUpdatingFromMonths.value = true;
                selectedQuarter.value = 'all';
            }
        }
        else if (selectedQuarter.value !== 'all') {
            isUpdatingFromMonths.value = true;
            selectedQuarter.value = 'all';
        }
    }

    // --- (2) ✅ [เพิ่มใหม่] อัปเดตปุ่ม TimeRange (1M, 2M, ...) ---
    const availableMonths = getAvailableMonths(selectedYear.value);
    let matchedRange: string | null = null;
    if (availableMonths.length > 0) {
        if (sortedMonths === availableMonths.slice(-1).join(',')) matchedRange = '1M';
        else if (sortedMonths === availableMonths.slice(-2).join(',')) matchedRange = '2M';
        else if (sortedMonths === availableMonths.slice(-3).join(',')) matchedRange = '3M';
        else if (sortedMonths === availableMonths.slice(-6).join(',')) matchedRange = '6M';
        else if (sortedMonths === availableMonths.join(',')) matchedRange = 'YTD';
    }
    // อัปเดต v-model ของ v-btn-toggle
    selectedTimeRange.value = matchedRange;

    // --- (3) ดึงข้อมูล ---
    fetchSummary();
    fetchDetailedTableData();
}, { deep: true });

const updateToAllMonths = () => {
    const yearAD = selectedYear.value - 543;
    if (yearAD === currentJsYear) {
        selectedMonths.value = allMonthItems.map(m => m.value).filter(m => m <= currentJsMonth);
    } else if (yearAD > currentJsYear) {
        selectedMonths.value = [];
    } else {
        selectedMonths.value = allMonthItems.map(m => m.value);
    }
};

onMounted(() => {
    updateToAllMonths();
});

// (9) Computed สำหรับหัวตารางรายเดือน
const displayedMonths = computed(() => {
    const selectedMonthValues = selectedMonths.value;
    return allMonthItems
        .filter(m => selectedMonthValues.includes(m.value))
        .sort((a, b) => a.value - b.value);
});


// (10) ฟังก์ชัน getValue (สำหรับกราฟ/การ์ด)
const getValue = (type: string, range: string) => {
    // @ts-ignore
    return summaryData.value?.[type]?.[range] || 0;
};

// ✅ [เพิ่มใหม่] Helper สำหรับ format ค่าบน Card (ตาม Req 2)
const formatCardValue = (type: string, range: string): string => {
    // @ts-ignore
    const value = summaryData.value?.[type]?.[range] || 0;
    if (type === 'unit') {
        return value.toLocaleString('th-TH', { maximumFractionDigits: 0 });
    }
    // ♻️ [แก้ไข] แสดงทศนิยม 2 ตำแหน่ง
    return value.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}


// (10.1) Export Excel
// ♻️ [แก้ไข] อัปเดตฟังก์ชันนี้ทั้งหมด
const exportToExcel = async () => {
    exportLoading.value = true; // (!!!) เพิ่ม
    showSnackbar.value = false; // (!!!) เพิ่ม

    try { // (!!!) เพิ่ม
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet('Monthly Summary');
        const fontName = 'TH Sarabun PSK'; // ✅ [ใหม่] กำหนดฟอนต์

        // --- 1. Title Row ---
        const title = `ตารางสรุปยอดรายเดือน (แยกตามมูลค่า) ${filterSubtitle.value}`;
        const titleRow = worksheet.addRow([title]);
        titleRow.font = { name: fontName, bold: true, size: 16 }; // ✅ [ใช้ฟอนต์]
        worksheet.mergeCells(titleRow.number, 1, titleRow.number, displayedMonths.value.length + 2);

        // --- 2. Header Row ---
        const headerRowData: string[] = [''];
        displayedMonths.value.forEach(month => headerRowData.push(month.short));
        headerRowData.push('รวม');

        const headerRow = worksheet.addRow(headerRowData);
        headerRow.font = { name: fontName, bold: true, size: 12 }; // ✅ [ใช้ฟอนต์]
        headerRow.alignment = { horizontal: 'center' };
        const totalCell = headerRow.getCell(headerRowData.length);
        totalCell.font = { name: fontName, bold: true, size: 12, color: { argb: 'FFFF0000' } }; // สีแดง
        totalCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFF3E0' } };

        // Helper function สำหรับกำหนด format ตัวเลข
        const getNumFmt = (field: string) => (field === 'unit' ? '#,##0' : '#,##0.00');

        // --- 3. Data Rows (วนตาม Price Range) ---
        priceRanges.forEach((range) => {
            // 3.1: แถวชื่อช่วงราคา
            const rangeTitleRow = worksheet.addRow([range]);
            rangeTitleRow.font = { name: fontName, bold: true, size: 12, color: { argb: 'FF725AF2' } }; // ✅ [ใช้ฟอนต์]
            rangeTitleRow.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
            worksheet.mergeCells(rangeTitleRow.number, 1, rangeTitleRow.number, headerRowData.length);

            // 3.2: แถวข้อมูลย่อย
            dataTypes.forEach((field) => {
                const metricLabel = dataTypeLabels[field];
                const rowData: (string | number)[] = [`  ${metricLabel}`];
                const numFmt = getNumFmt(field);
                const fieldKey = field as keyof PriceRangeMetrics;

                // เพิ่มข้อมูลรายเดือน
                displayedMonths.value.forEach(month => {
                    rowData.push(getNumericDetailedValue(fieldKey, month.value, range));
                });

                // เพิ่มข้อมูล "ผลรวมแนวนอน" (คอลัมน์ รวม)
                if (fieldKey === 'price_per_sqm') {
                    const totalValue = getHorizontalTotal(range, 'total_value');
                    const totalArea = getHorizontalTotal(range, 'usable_area');
                    rowData.push(totalArea > 0 ? totalValue / totalArea : 0);
                } else {
                    rowData.push(getHorizontalTotal(range, fieldKey));
                }

                const dataRow = worksheet.addRow(rowData);
                dataRow.font = { name: fontName, size: 11 }; // ✅ [ใช้ฟอนต์]

                // กำหนดสไตล์และ Format
                dataRow.getCell(1).alignment = { horizontal: 'left' };
                for (let i = 2; i <= rowData.length; i++) {
                    const cell = dataRow.getCell(i);
                    cell.numFmt = numFmt;
                    cell.alignment = { horizontal: 'right' };
                    if (i === rowData.length) {
                        cell.font = { name: fontName, bold: true, size: 11 }; // ✅ [ใช้ฟอนต์]
                        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFF3E0' } };
                    }
                }

                // ✅ [ใหม่] เพิ่มแถว MoM%
                // (เราใช้ showMoM.value ที่มาจากฟิลเตอร์บนหน้าจอ)
                if (showMoM.value) {
                    const momRowData: (string | number)[] = [`  (MoM%)`];
                    displayedMonths.value.forEach(month => {
                        const momValue = getMoMCellData(range, month.value, fieldKey);
                        // แปลง 10.5 -> 0.105 (สำหรับ Excel)
                        momRowData.push(momValue !== null ? momValue / 100 : '-');
                    });
                    momRowData.push(''); // คอลัมน์ "รวม" ของ MoM ไม่มี

                    const momRow = worksheet.addRow(momRowData);
                    momRow.font = { name: fontName, italic: true, size: 10, color: { argb: 'FF555555' } }; // ✅ [ใช้ฟอนต์]

                    // สไตล์สำหรับแถว MoM
                    momRow.getCell(1).alignment = { horizontal: 'left' };
                    for (let i = 2; i <= momRowData.length; i++) {
                        const cell = momRow.getCell(i);
                        if (typeof cell.value === 'number') {
                            cell.numFmt = '0.0"%"'; // Format เป็น %
                            cell.alignment = { horizontal: 'right' };
                        } else {
                            cell.alignment = { horizontal: 'center' };
                        }
                    }
                }
            });
        });

        // --- 4. Grand Total Rows ---
        const totalTitleRow = worksheet.addRow(['']);
        totalTitleRow.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.mergeCells(totalTitleRow.number, 1, totalTitleRow.number, headerRowData.length);

        dataTypes.forEach((field) => {
            const metricLabel = dataTypeLabels[field];
            const rowData: (string | number)[] = [`${metricLabel} (รวม)`];
            const numFmt = getNumFmt(field);
            const fieldKey = field as keyof PriceRangeMetrics;

            // ผลรวมแนวตั้ง (รายเดือน)
            displayedMonths.value.forEach(month => {
                if (fieldKey === 'price_per_sqm') {
                    const totalValue = getMonthTotal(month.value, 'total_value');
                    const totalArea = getMonthTotal(month.value, 'usable_area');
                    rowData.push(totalArea > 0 ? totalValue / totalArea : 0);
                } else {
                    rowData.push(getMonthTotal(month.value, fieldKey));
                }
            });

            // ผลรวมทั้งหมด (Grand Total)
            if (fieldKey === 'price_per_sqm') {
                const totalValue = getGrandTotal('total_value');
                const totalArea = getGrandTotal('usable_area');
                rowData.push(totalArea > 0 ? totalValue / totalArea : 0);
            } else {
                rowData.push(getGrandTotal(fieldKey));
            }

            const totalRow = worksheet.addRow(rowData);
            totalRow.font = { name: fontName, bold: true, size: 12, color: { argb: 'FFF8285A' } }; // ✅ [ใช้ฟอนต์]

            totalRow.getCell(1).alignment = { horizontal: 'left' };
            for (let i = 2; i <= rowData.length; i++) {
                const cell = totalRow.getCell(i);
                cell.numFmt = numFmt;
                cell.alignment = { horizontal: 'right' };
                if (i === rowData.length) {
                    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFF3E0' } };
                }
            }

            // ✅ [ใหม่] แถว MoM% สำหรับ Total
            if (showMoM.value) {
                const momRowData: (string | number)[] = [`  (MoM%)`];
                displayedMonths.value.forEach(month => {
                    const momValue = getMonthTotalMoM(month.value, fieldKey);
                    momRowData.push(momValue !== null ? momValue / 100 : '-');
                });
                momRowData.push(''); // "รวม" ไม่มี MoM

                const momRow = worksheet.addRow(momRowData);
                momRow.font = { name: fontName, italic: true, size: 10, color: { argb: 'FF555555' } }; // ✅ [ใช้ฟอนต์]
                momRow.getCell(1).alignment = { horizontal: 'left' };
                for (let i = 2; i <= momRowData.length; i++) {
                    const cell = momRow.getCell(i);
                    if (typeof cell.value === 'number') {
                        cell.numFmt = '0.0"%"';
                        cell.alignment = { horizontal: 'right' };
                    } else {
                        cell.alignment = { horizontal: 'center' };
                    }
                }
            }
        });

        // --- 5. Set Column Widths ---
        worksheet.getColumn(1).width = 30;
        for (let i = 2; i <= headerRowData.length; i++) {
            worksheet.getColumn(i).width = 18;
        }

        // --- 6. Write file ---
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${dynamicFilename.value}.xlsx`; // ✅ [ใช้ชื่อไฟล์ใหม่]
        a.click();
        window.URL.revokeObjectURL(url);

        // (!!!) เพิ่ม
        snackbarText.value = 'สร้างไฟล์ Excel สำเร็จแล้ว';
        snackbarColor.value = 'success';
        showSnackbar.value = true;

    } catch (err: any) { // (!!!) เพิ่ม
        console.error('Error exporting to Excel:', err);
        snackbarText.value = `ไม่สามารถสร้าง Excel ได้: ${err.message || 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ'}`;
        snackbarColor.value = 'error';
        showSnackbar.value = true;
    } finally { // (!!!) เพิ่ม
        exportLoading.value = false;
    }
};

const dynamicFilename = computed(() => {
    // จะได้ค่าประมาณ "(ประจำปี 2568 - ไตรมาส 1 (มกราคม - มีนาคม))"
    const cleanedSubtitle = filterSubtitle.value
        .replace(/[\(\)]/g, '') // ลบวงเล็บ
        .replace(/\s+/g, '_')   // แทนที่ช่องว่างด้วย _
        .replace(/__/g, '_')
        .replace(/_-_/g, '_');

    // e.g., "รายงานสรุปยอดรายเดือน_ประจำปี_2568_ไตรมาส_1"
    const baseName = `รายงานสรุปยอดรายเดือน${cleanedSubtitle || ''}`;

    // ลบอักขระที่ไม่ปลอดภัยสำหรับชื่อไฟล์ (เผื่อไว้)
    return baseName.replace(/[/\\?%*:|"<>]/g, '');
});

// (ฟังก์ชันนี้เหมือน getDetailedValue แต่คืนค่าเป็นตัวเลข)
function getNumericDetailedValue(type: keyof PriceRangeMetrics, monthValue: number, range: string): number {
    return detailedTableData.value?.[monthValue]?.[range]?.[type] || 0;
}

// 1. ผลรวมแนวนอน (รวมทุกเดือน)
function getHorizontalTotal(priceRange: string, field: keyof PriceRangeMetrics): number {
    return displayedMonths.value.reduce((total, month) => {
        return total + getNumericDetailedValue(field, month.value, priceRange);
    }, 0);
}

// 2. ผลรวมแนวตั้ง (รวมทุกช่วงราคา)
function getMonthTotal(monthValue: number, field: keyof PriceRangeMetrics): number {
    return priceRanges.reduce((total, range) => {
        return total + getNumericDetailedValue(field, monthValue, range);
    }, 0);
}

// 3. ผลรวมทั้งหมด (รวมทุกเดือนและทุกช่วงราคา)
function getGrandTotal(field: keyof PriceRangeMetrics): number {
    return displayedMonths.value.reduce((total, month) => {
        return total + getMonthTotal(month.value, field);
    }, 0);
}

// 4. ฟังก์ชันสำหรับจัดรูปแบบ (จัดการ 'price_per_sqm' ที่ต้องคำนวณใหม่)

// (ผลรวมแนวนอน)
// ♻️ [แก้ไข] ปรับการแสดงผลทศนิยม
function getFormattedHorizontalTotal(priceRange: string, field: keyof PriceRangeMetrics): string {
    if (field === 'price_per_sqm') {
        const totalValue = getHorizontalTotal(priceRange, 'total_value');
        const totalArea = getHorizontalTotal(priceRange, 'usable_area');
        const avg = totalArea > 0 ? totalValue / totalArea : 0;
        return avg.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    const total = getHorizontalTotal(priceRange, field);

    if (field === 'unit') {
        return total.toLocaleString('th-TH', { maximumFractionDigits: 0 });
    }
    return total.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// (ผลรวมแนวตั้ง)
// ♻️ [แก้ไข] ปรับการแสดงผลทศนิยม
function getFormattedMonthTotal(monthValue: number, field: keyof PriceRangeMetrics): string {
    if (field === 'price_per_sqm') {
        const totalValue = getMonthTotal(monthValue, 'total_value');
        const totalArea = getMonthTotal(monthValue, 'usable_area');
        const avg = totalArea > 0 ? totalValue / totalArea : 0;
        return avg.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    const total = getMonthTotal(monthValue, field);

    if (field === 'unit') {
        return total.toLocaleString('th-TH', { maximumFractionDigits: 0 });
    }
    return total.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// (ผลรวมทั้งหมด)
// ♻️ [แก้ไข] ปรับการแสดงผลทศนิยม
function getFormattedGrandTotal(field: keyof PriceRangeMetrics): string {
    if (field === 'price_per_sqm') {
        const totalValue = getGrandTotal('total_value');
        const totalArea = getGrandTotal('usable_area');
        const avg = totalArea > 0 ? totalValue / totalArea : 0;
        return avg.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    const total = getGrandTotal(field);

    if (field === 'unit') {
        return total.toLocaleString('th-TH', { maximumFractionDigits: 0 });
    }
    return total.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}


const filterSubtitle = computed(() => {
    // ... (โค้ดส่วนนี้เหมือนเดิม) ...
    const yearText = `ประจำปี ${selectedYear.value}`;
    const sortedMonthsKey = [...selectedMonths.value].sort((a, b) => a - b).join(',');
    if (sortedMonthsKey === '1,2,3') return `(${yearText} - ไตรมาส 1 (มกราคม - มีนาคม))`;
    if (sortedMonthsKey === '4,5,6') return `(${yearText} - ไตรมาส 2 (เมษายน - มิถุนายน))`;
    if (sortedMonthsKey === '7,8,9') return `(${yearText} - ไตรมาส 3 (กรกฎาคม - กันยายน))`;
    if (sortedMonthsKey === '10,11,12') return `(${yearText} - ไตรมาส 4 (ตุลาคม - ธันวาคม))`;
    const yearAD = selectedYear.value - 543;
    const allMonthsCurrentYear = allMonthItems.map(m => m.value).slice(0, currentJsMonth).join(',');
    const allMonthsPastYear = allMonthItems.map(m => m.value).join(',');
    if (sortedMonthsKey === allMonthsCurrentYear || sortedMonthsKey === allMonthsPastYear) {
        const allOption = quarterOptions.value.find(q => q.value === 'all');
        return `(${yearText} - ${allOption ? allOption.title : 'ทุกเดือน'})`;
    }
    if (selectedMonths.value.length > 0) {
        const sortedMonthValues = [...selectedMonths.value].sort((a, b) => a - b);
        const firstMonthValue = sortedMonthValues[0];
        const lastMonthValue = sortedMonthValues[sortedMonthValues.length - 1];
        const firstMonth = allMonthItems.find(m => m.value === firstMonthValue);
        const lastMonth = allMonthItems.find(m => m.value === lastMonthValue);
        if (!firstMonth || !lastMonth) {
            return `(${yearText} - กำลังเลือกเดือน...)`;
        }
        if (firstMonthValue === lastMonthValue) {
            return `(${yearText} - เดือน ${firstMonth.title})`;
        } else {
            return `(${yearText} - เดือน ${firstMonth.title} - ${lastMonth.title})`;
        }
    }
    return `(${yearText} - ยังไม่ได้เลือกเดือน)`;
});


const cardLabels = ['จำนวนหลัง', 'มูลค่ารวม', 'พื้นที่ใช้สอย', 'ราคาเฉลี่ย/ตร.ม.'] as const;
const selectedHighlight = ref<(typeof cardLabels)[number] | null>(null);

function highlightRow(label: (typeof cardLabels)[number]) {
    if (selectedHighlight.value === label) {
        selectedHighlight.value = null;
    } else {
        selectedHighlight.value = label;
    }
}

const polarAreaSeries = computed(() => {
    const metricKey =
        (selectedHighlight.value === 'จำนวนหลัง') ? 'unit' :
            (selectedHighlight.value === 'พื้นที่ใช้สอย') ? 'usable_area' :
                (selectedHighlight.value === 'ราคาเฉลี่ย/ตร.ม.') ? 'price_per_sqm' :
                    'total_value';
    if (!summaryData.value[metricKey]) return [];
    // @ts-ignore
    const newSeries = priceRanges.map(range => summaryData.value[metricKey][range] || 0);
    const totalSum = newSeries.reduce((a, b) => a + b, 0);
    return totalSum > 0 ? newSeries : [];
});

function isRowVisible(label: string): boolean {
    if (selectedHighlight.value === null) {
        return true;
    }
    return selectedHighlight.value === label;
}

function getHighlightStyle(label: string) {
    if (selectedHighlight.value !== label) return null;
    if (label === 'จำนวนหลัง') return { backgroundColor: '#E3F2FD' };
    if (label === 'มูลค่ารวม') return { backgroundColor: '#EDE7F6' };
    if (label === 'พื้นที่ใช้สอย') return { backgroundColor: '#FFEBEE' };
    if (label === 'ราคาเฉลี่ย/ตร.ม.') return { backgroundColor: '#FFF8E1' };
    return null;
}

const tableUnitSubtitle = computed(() => {
    const selected = selectedHighlight.value;
    if (selected === 'จำนวนหลัง') return '(หน่วย : หลัง)';
    if (selected === 'มูลค่ารวม') return '(หน่วย : บาท)';
    if (selected === 'พื้นที่ใช้สอย') return '(หน่วย : ตร.ม.)';
    if (selected === 'ราคาเฉลี่ย/ตร.ม.') return '(หน่วย : บาท / ตร.ม.)';
    return '';
});

const chartMoMData = computed(() => {
    if (selectedMonths.value.length === 0) {
        // ถ้าไม่มีเดือนที่เลือก
        return { percent: null, label: '', hasPreviousMonth: false };
    }

    // 1. หาเดือน "สุดท้าย" ในช่วงที่ผู้ใช้เลือก
    const lastSelectedMonth = Math.max(...selectedMonths.value);

    // 2. กำหนดเดือนก่อนหน้า
    const prevMonthValue = lastSelectedMonth - 1;

    // 3. ตรวจสอบว่าเปรียบเทียบได้หรือไม่
    // (เทียบไม่ได้ ถ้าเป็นเดือน ม.ค. หรือ ถ้าไม่มีข้อมูลเดือนก่อนหน้า)
    if (lastSelectedMonth === 1 || !detailedTableData.value[prevMonthValue]) {
        const label = lastSelectedMonth === 1 ? '(ม.ค. ไม่มีข้อมูล MoM)' : '';
        return { percent: null, label: label, hasPreviousMonth: false };
    }

    // 4. หา metric key ตามที่ผู้ใช้กำลังไฮไลต์ (หรือใช้ 'total_value' เป็นค่าเริ่มต้น)
    const metricKey =
        (selectedHighlight.value === 'จำนวนหลัง') ? 'unit' :
            (selectedHighlight.value === 'พื้นที่ใช้สอย') ? 'usable_area' :
                (selectedHighlight.value === 'ราคาเฉลี่ย/ตร.ม.') ? 'price_per_sqm' :
                    'total_value'; // ค่าเริ่มต้น

    // 5. ใช้ฟังก์ชันเดิม (getMonthTotalMoM) ที่คุณมีอยู่แล้ว
    // เพื่อคำนวณ MoM% ของ "ผลรวม"
    const percent = getMonthTotalMoM(lastSelectedMonth, metricKey);

    // 6. สร้าง Label
    const prevMonthShort = allMonthItems.find(m => m.value === prevMonthValue)?.short || '';
    const label = `MoM % (เทียบ ${prevMonthShort}.)`;

    return { percent: percent, label: label, hasPreviousMonth: true };
});

function calculatePercentageChange(currentValue: number, previousValue: number): number | null {
    if (previousValue > 0) {
        const change = ((currentValue - previousValue) / previousValue) * 100;
        return parseFloat(change.toFixed(1));
    } else if (currentValue > 0) {
        return 100;
    } else if (previousValue === 0 && currentValue === 0) {
        return 0;
    }
    return null;
}
function formatPercentage(value: number | null): string {
    if (value === null) return ''; // [แก้ไข] เปลี่ยนจาก '-' เป็น ''
    if (value === 0) return ''; // [แก้ไข] เปลี่ยนจาก '0.0%' เป็น ''
    const prefix = value > 0 ? '+' : '';
    return `(${prefix}${value.toFixed(1)}%)`; // [แก้ไข] ย้ายวงเล็บมาไว้ในฟังก์ชันนี้
};

function getPercentageColor(value: number | null): string {
    if (value === null) return 'text-grey';
    if (value > 0) return 'text-success';
    if (value < 0) return 'text-error';
    return 'text-grey';
};

// ----------------------------------------------------------------
// ✅ [เพิ่มใหม่] (14) MoM% (Month-over-Month) Calculation
// ----------------------------------------------------------------

function getMoMCellData(priceRange: string, monthValue: number, metric: keyof PriceRangeMetrics): number | null {
    const prevMonthValue = monthValue - 1;
    if (monthValue === 1 || !detailedTableData.value[prevMonthValue]) {
        return null;
    }
    const currentValue = getNumericDetailedValue(metric, monthValue, priceRange);
    const previousValue = getNumericDetailedValue(metric, prevMonthValue, priceRange);
    return calculatePercentageChange(currentValue, previousValue);
}

function getMonthTotalMoM(monthValue: number, metric: keyof PriceRangeMetrics): number | null {
    const prevMonthValue = monthValue - 1;
    if (monthValue === 1 || !detailedTableData.value[prevMonthValue]) {
        return null;
    }
    if (metric === 'price_per_sqm') {
        const currentTotalValue = getMonthTotal(monthValue, 'total_value');
        const currentTotalArea = getMonthTotal(monthValue, 'usable_area');
        const currentAvg = currentTotalArea > 0 ? (currentTotalValue / currentTotalArea) : 0;
        const prevTotalValue = getMonthTotal(prevMonthValue, 'total_value');
        const prevTotalArea = getMonthTotal(prevMonthValue, 'usable_area');
        const prevAvg = prevTotalArea > 0 ? (prevTotalValue / prevTotalArea) : 0;
        return calculatePercentageChange(currentAvg, prevAvg);
    }
    const currentValue = getMonthTotal(monthValue, metric);
    const previousValue = getMonthTotal(prevMonthValue, metric);
    return calculatePercentageChange(currentValue, previousValue);
}


// ----------------------------------------------------------------
// ✅ [เพิ่มใหม่] (15) YoY% (Year-over-Year) Calculation
// ----------------------------------------------------------------

function getRawData(
    dataSource: Record<number, Record<string, PriceRangeMetrics>>,
    monthValue: number,
    priceRange: string,
    metric: keyof PriceRangeMetrics
): number {
    return dataSource?.[monthValue]?.[priceRange]?.[metric] || 0;
}

// ⭐️ [ใหม่] Computed สำหรับ YTD% (YoY) ของการ์ดสรุป
// (ใช้ logic เดียวกับ region.vue แต่เรียกใช้ getGrandTotalYoY ที่มีอยู่แล้วใน house.vue)
const summaryCardYTDData = computed(() => {

    // ตรวจสอบว่ามีข้อมูลปีก่อนหรือไม่ (house.vue มี state นี้อยู่แล้ว)
    if (Object.keys(previousYearDetailedTableData.value).length === 0) {
        return { unit: null, total_value: null, usable_area: null, price_per_sqm: null };
    }

    const ytdValues: Record<string, string | null> = {};

    // ⭐️ ใช้ dataTypes (['unit', 'total_value', ...]) ที่มีใน house.vue
    for (const field of dataTypes) {

        // ⭐️ เรียกใช้ฟังก์ชัน getGrandTotalYoY (line 970) ที่มีอยู่แล้วใน house.vue
        const percent = getGrandTotalYoY(field as keyof PriceRangeMetrics);

        if (percent === null) {
            ytdValues[field] = null;
        } else {
            const percentStr = percent.toFixed(0); // ไม่เอาทศนิยม
            if (percent > 0) {
                ytdValues[field] = `(+${percentStr}%)`;
            } else if (percent < 0) {
                ytdValues[field] = `(${percentStr}%)`;
            } else {
                ytdValues[field] = null; // ไม่แสดง 0%
            }
        }
    }

    // ⭐️ ต้องคืนค่าให้ครบ 4 keys ของ house.vue
    return ytdValues as Record<keyof PriceRangeMetrics, string | null>;
});

function getRowTotalYoY(priceRange: string, metric: keyof PriceRangeMetrics): number | null {
    if (metric === 'price_per_sqm') {
        let currentTotalValue = 0;
        let currentTotalArea = 0;
        let prevTotalValue = 0;
        let prevTotalArea = 0;
        for (const month of displayedMonths.value) {
            currentTotalValue += getRawData(detailedTableData.value, month.value, priceRange, 'total_value');
            currentTotalArea += getRawData(detailedTableData.value, month.value, priceRange, 'usable_area');
            prevTotalValue += getRawData(previousYearDetailedTableData.value, month.value, priceRange, 'total_value');
            prevTotalArea += getRawData(previousYearDetailedTableData.value, month.value, priceRange, 'usable_area');
        }
        const currentAvg = currentTotalArea > 0 ? (currentTotalValue / currentTotalArea) : 0;
        const previousAvg = prevTotalArea > 0 ? (prevTotalValue / prevTotalArea) : 0;
        return calculatePercentageChange(currentAvg, previousAvg);
    }

    let currentValue = 0;
    let previousValue = 0;
    for (const month of displayedMonths.value) {
        currentValue += getRawData(detailedTableData.value, month.value, priceRange, metric);
        previousValue += getRawData(previousYearDetailedTableData.value, month.value, priceRange, metric);
    }
    return calculatePercentageChange(currentValue, previousValue);
}

function getGrandTotalYoY(metric: keyof PriceRangeMetrics): number | null {
    if (metric === 'price_per_sqm') {
        const currentGrandTotalValue = getGrandTotal('total_value');
        const currentGrandTotalArea = getGrandTotal('usable_area');
        const currentAvg = currentGrandTotalArea > 0 ? (currentGrandTotalValue / currentGrandTotalArea) : 0;
        let prevGrandTotalValue = 0;
        let prevGrandTotalArea = 0;
        for (const month of displayedMonths.value) {
            for (const range of priceRanges) {
                prevGrandTotalValue += getRawData(previousYearDetailedTableData.value, month.value, range, 'total_value');
                prevGrandTotalArea += getRawData(previousYearDetailedTableData.value, month.value, range, 'usable_area');
            }
        }
        const previousAvg = prevGrandTotalArea > 0 ? (prevGrandTotalValue / prevGrandTotalArea) : 0;
        return calculatePercentageChange(currentAvg, previousAvg);
    }
    const currentValue = getGrandTotal(metric);
    let previousValue = 0;
    for (const month of displayedMonths.value) {
        for (const range of priceRanges) {
            previousValue += getRawData(previousYearDetailedTableData.value, month.value, range, metric);
        }
    }
    return calculatePercentageChange(currentValue, previousValue);
}


// (!!!) 3. เพิ่ม Helper Function (คัดลอกมาจาก shadow.vue)
const blobToDataURL = (blob: Blob): Promise<string> => {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            resolve(reader.result as string);
        };
        reader.onerror = (error) => reject(error);
        reader.readAsDataURL(blob);
    });
};


// (!!!) 4. ลบ getFontBase64 และ exportToPdf (เวอร์ชัน autoTable) เดิมทิ้ง
// ... (โค้ด 2 ฟังก์ชันนี้ถูกลบไปแล้ว) ...


// (!!!) 5. เพิ่มฟังก์ชัน exportToPdf (เวอร์ชัน html2canvas)
const exportToPdf = async () => {
    exportLoading.value = true;
    showSnackbar.value = false;

    // (!!!) 6a. เปลี่ยน ID ตาราง
    const tableElement = document.getElementById('house-table-to-export');
    if (!tableElement) {
        console.error('Table element not found!');
        snackbarText.value = 'ไม่พบองค์ประกอบตาราง (Table element not found!)';
        snackbarColor.value = 'error';
        showSnackbar.value = true;
        exportLoading.value = false;
        return;
    }

    try {
        // === 1. โหลดทรัพยากร (ฟอนต์) ===
        const fontUrl = '/fonts/THSarabunNew.ttf';
        let fontBase64 = '';
        try {
            const fontResponse = await fetch(fontUrl);
            if (!fontResponse.ok) throw new Error('Failed to fetch local font: /fonts/THSarabunNew.ttf');
            const fontBlob = await fontResponse.blob();
            const fontDataURL = await blobToDataURL(fontBlob);
            fontBase64 = fontDataURL.split(',')[1];
            if (!fontBase64) throw new Error('Failed to parse Base64 from font Data URL');

        } catch (fontErr: any) {
            throw new Error(`Font loading failed: ${fontErr.message}`);
        }

        // (!!!) 6b. ลบการโหลด Logo ออก

        const logoUrl = '/assets/images/image-28-2.png'; // (!!!) ตรวจสอบว่า path นี้ถูกต้อง
        let logoDataURL = '';
        try {
            const logoResponse = await fetch(logoUrl);
            if (!logoResponse.ok) throw new Error('Logo file not found in /assets/images/image-28-2.png');
            const logoBlob = await logoResponse.blob();

            logoDataURL = await blobToDataURL(logoBlob);
        } catch (imgErr) {
            console.error(imgErr); // ถ้าหาโลโก้ไม่เจอ ก็จะแค่ข้ามไป ไม่ให้ error
        }


        // === 2. สร้าง PDF และตั้งค่าฟอนต์ ===
        // (!!!) 6c. เปลี่ยนเป็นแนวนอน ('l' for landscape)
        const pdf = new jsPDF('p', 'mm', 'a4');
        const fontName = 'THSarabunNew';
        const fontStyle = 'normal';

        pdf.addFileToVFS('THSarabunNew.ttf', atob(fontBase64));
        pdf.addFont('THSarabunNew.ttf', fontName, fontStyle);
        pdf.setFont(fontName, fontStyle);

        // === 3. สร้างหน้าปก (ข้อความ) ===
        // === 3. สร้างหน้าปก (ข้อความ) ===
        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageCenter = pageWidth / 2;

        // (!!!) 6d. (แก้ไข) ปรับ Y และเพิ่มโลโก้
        let currentY = 90; // (!!!) ปรับตำแหน่งเริ่มต้นสำหรับโลโก้ (ถ้าใช้แนวตั้ง)

        // (!!!) 6d. (เพิ่ม) วาดโลโก้
        if (logoDataURL) {
            const logoWidth = 60;
            const logoHeight = 70;
            const logoX = pageCenter - (logoWidth / 2);

            pdf.addImage(logoDataURL, 'PNG', logoX, currentY, logoWidth, logoHeight);

            currentY += logoHeight + 15; // ขยับ Y ลงมาใต้โลโก้
        } else {
            currentY = 100; // กลับไปค่าเดิมถ้าไม่มีโลโก้ (หรือปรับตามความเหมาะสม)
        }

        // (!!!) 6e. เปลี่ยนข้อความเป็นของ house.vue
        pdf.setFontSize(20);
        // ... (โค้ดส่วนที่เหลือสำหรับวาด Title และ DateString เหมือนเดิม) ...

        const title = `ตารางสรุปยอดรายเดือน (แยกตามมูลค่า) ${filterSubtitle.value}`;
        // ใช้ autoTable (หรือ text) เพื่อจัดการข้อความยาว
        const splitTitle = pdf.splitTextToSize(title, pageWidth - 40); // 40 = margin
        pdf.text(splitTitle, pageCenter, currentY, { align: 'center' });
        currentY += (splitTitle.length * 7); // ปรับ Y ตามจำนวนบรรทัด

        // (!!!) 6f. ลบวันที่อัพเดต (ถ้าไม่ต้องการ) หรือเก็บไว้
        const today = new Date();
        const day = today.getDate();
        const monthIndex = today.getMonth();
        const year = today.getFullYear() + 543; // ปี พ.ศ.
        const thaiMonths = [
            "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
            "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
        ];
        const month = thaiMonths[monthIndex];
        const dateString = `อัพเดตข้อมูลวันที่ ${day} ${month} ${year}`;

        currentY += 10;
        pdf.setFontSize(15);
        pdf.text(dateString, pageCenter, currentY, { align: 'center' });
        // === จบหน้าปก ===

        // 7. ถ่ายรูปตาราง
        const canvas = await html2canvas(tableElement, {
            scale: 2,
            useCORS: true,
            logging: false,
        });

        const imgData = canvas.toDataURL('image/png');

        // 8. เพิ่มหน้าใหม่สำหรับตาราง
        pdf.addPage();

        // 9. คำนวณสัดส่วน
        const pageMargin = 10;
        const pdfWidth = pdf.internal.pageSize.getWidth() - (pageMargin * 2);
        const pdfHeight = pdf.internal.pageSize.getHeight() - (pageMargin * 2);

        // (!!!) 6g. ปรับสัดส่วนรูปภาพ (Fit by Width)
        const imgWidth = pdfWidth; // 1. ยึดความกว้าง 100% เป็นหลัก
        const imgHeight = canvas.height * imgWidth / canvas.width; // 2. คำนวณความสูงตามสัดส่วน

        let heightLeft = imgHeight;
        let position = pageMargin;

        pdf.addImage(imgData, 'PNG', pageMargin, position, imgWidth, imgHeight);
        heightLeft -= pdfHeight;

        // 10. วนลูปเพิ่มหน้า (เหมือนเดิม)
        while (heightLeft > 0) {
            position = heightLeft - imgHeight + pageMargin;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', pageMargin, position, imgWidth, imgHeight);
            heightLeft -= pdfHeight;
        }

        // 11. บันทึกไฟล์
        // (!!!) 6h. เปลี่ยนชื่อไฟล์
        pdf.save(`${dynamicFilename.value}.pdf`);

        snackbarText.value = 'สร้างไฟล์ PDF สำเร็จแล้ว';
        snackbarColor.value = 'success';
        showSnackbar.value = true;

    } catch (err: any) {
        console.error('Error exporting to PDF:', err);
        snackbarText.value = `ไม่สามารถสร้าง PDF ได้: ${err.message || 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ'}`;
        snackbarColor.value = 'error';
        showSnackbar.value = true;

    } finally {
        exportLoading.value = false;
    }
};
</script>

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
                            <h3 class="text-h5 card-title">รายงานยอดเซ็นสัญญาแบ่งตามมูลค่าบ้าน</h3>
                            <ul
                                class="v-breadcrumbs v-breadcrumbs--density-default text-subtitle-1 textSecondary pa-0 ml-n1">
                                <li class="v-breadcrumbs-item" text="Home"><a class="v-breadcrumbs-item--link" href="#">
                                        <p>หน้าแรก</p>
                                    </a></li>
                                <li class="v-breadcrumbs-divider"><i
                                        class="mdi-chevron-right mdi v-icon notranslate v-theme--BLUE_THEME"
                                        aria-hidden="true" style="font-size: 15px; height: 15px; width: 15px"></i></li>
                                <li class="v-breadcrumbs-item" text="Dashboard"><a class="v-breadcrumbs-item--link"
                                        href="#">
                                        <p>รายงานยอดเซ็นสัญญาแบ่งตามมูลค่าบ้าน</p>
                                    </a></li>
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
                        <v-col cols="12" sm="4" md="4"><v-select v-model="selectedYear" :items="yearOptions"
                                label="ปี (พ.ศ.)" density="compact" variant="outlined" hide-details /></v-col>
                        <v-col cols="12" sm="4" md="4"><v-select v-model="selectedQuarter" :items="quarterOptions"
                                item-title="title" item-value="value" label="ไตรมาส" density="compact"
                                variant="outlined" hide-details /></v-col>
                        <v-col cols="12" sm="4" md="4"><v-select v-model="selectedMonths" :items="monthOptions"
                                item-title="title" item-value="value" label="เดือน (เลือกได้หลายเดือน)" multiple chips
                                closable-chips density="compact" variant="outlined" hide-details /></v-col>
                    </v-row>


                </v-card-text>
            </v-card>
        </v-col>
        <v-col cols="12" sm="12" lg="12">
            <div class="v-row">
                <div v-for="(label, index) in cardLabels" :key="index" class="v-col-sm-6 v-col-lg-3 v-col-12 py-0 mb-3">

                    <div class="v-card v-theme--BLUE_THEME v-card--density-default elevation-10 rounded-md v-card--variant-elevated"
                        @click="highlightRow(label)" style="cursor: pointer;" hover
                        :class="{ 'card-is-active': selectedHighlight === label }">
                        <div class="v-card-text pa-5">
                            <div class="d-flex align-center ga-4">
                                <button type="button"
                                    class="v-btn v-btn--elevated v-btn--icon v-theme--BLUE_THEME v-btn--density-default v-btn--size-default v-btn--variant-elevated"
                                    :class="{ 'bg-primary': label === 'จำนวนหลัง', 'bg-secondary': label === 'มูลค่ารวม', 'bg-error': label === 'พื้นที่ใช้สอย', 'bg-warning': label === 'ราคาเฉลี่ย/ตร.ม.' }"
                                    dark>
                                    <svg v-if="label === 'จำนวนหลัง'" xmlns="http://www.w3.org/2000/svg" width="24"
                                        height="24" viewBox="0 0 24 24">
                                        <g fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path
                                                d="M2 12.204c0-2.289 0-3.433.52-4.381c.518-.949 1.467-1.537 3.364-2.715l2-1.241C9.889 2.622 10.892 2 12 2s2.11.622 4.116 1.867l2 1.241c1.897 1.178 2.846 1.766 3.365 2.715S22 9.915 22 12.203v1.522c0 3.9 0 5.851-1.172 7.063S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.212S2 17.626 2 13.725z" />
                                            <path stroke-linecap="round" d="M12 15v3" />
                                        </g>
                                    </svg>
                                    <svg v-else-if="label === 'มูลค่ารวม'" xmlns="http://www.w3.org/2000/svg" width="24"
                                        height="24" viewBox="0 0 24 24">
                                        <g fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path
                                                d="M2 14c0-3.771 0-5.657 1.172-6.828S6.229 6 10 6h4c3.771 0 5.657 0 6.828 1.172S22 10.229 22 14s0 5.657-1.172 6.828S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.172S2 17.771 2 14Zm14-8c0-1.886 0-2.828-.586-3.414S13.886 2 12 2s-2.828 0-3.414.586S8 4.114 8 6" />
                                            <path stroke-linecap="round"
                                                d="M12 17.333c1.105 0 2-.746 2-1.666S13.105 14 12 14s-2-.746-2-1.667c0-.92.895-1.666 2-1.666m0 6.666c-1.105 0-2-.746-2-1.666m2 1.666V18m0-8v.667m0 0c1.105 0 2 .746 2 1.666" />
                                        </g>
                                    </svg>
                                    <svg v-else-if="label === 'พื้นที่ใช้สอย'" xmlns="http://www.w3.org/2000/svg"
                                        width="24" height="24" viewBox="0 0 24 24">
                                        <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5">
                                            <path
                                                d="M11 2c-4.055.007-6.178.107-7.536 1.464C2 4.928 2 7.285 2 11.999s0 7.071 1.464 8.536C4.93 21.999 7.286 21.999 12 21.999s7.071 0 8.535-1.464c1.358-1.357 1.457-3.48 1.464-7.536" />
                                            <path stroke-linejoin="round"
                                                d="m13 11l9-9m0 0h-5.344M22 2v5.344M21 3l-9 9m0 0h4m-4 0V8" />
                                        </g>
                                    </svg>
                                    <svg v-else-if="label === 'ราคาเฉลี่ย/ตร.ม.'" xmlns="http://www.w3.org/2000/svg"
                                        width="24" height="24" viewBox="0 0 24 24">
                                        <g fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path
                                                d="M4.979 9.685C2.993 8.891 2 8.494 2 8s.993-.89 2.979-1.685l2.808-1.123C9.773 4.397 10.767 4 12 4s2.227.397 4.213 1.192l2.808 1.123C21.007 7.109 22 7.506 22 8s-.993.89-2.979 1.685l-2.808 1.124C14.227 11.603 13.233 12 12 12s-2.227-.397-4.213-1.191z" />
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
                                            {{ formatCardValue(dataTypes[index], 'total') }}
                                        </h2>
                                    </div>

                                    <span v-if="summaryCardYTDData[dataTypes[index]]" class="mom-card"
                                        :class="{
                                            'text-success': summaryCardYTDData[dataTypes[index]]?.includes('+'), 'text-error': summaryCardYTDData[dataTypes[index]]?.includes('-')
                                        }"
                                        style="font-size: 9px; font-weight: 400; line-height: 1.6; display: block;">
                                        {{ summaryCardYTDData[dataTypes[index]] }}
                                    </span>

                                    <p class="textSecondary text-15" style="line-height: 1.2;">
                                        {{ label }}
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
                    <div class="v-row">
                        <div class="v-col-md-8 v-col-12">
                            <div class="d-flex align-center">
                                <div>
                                    <h3 class="card-title mb-1">{{ chartTitle }}</h3>
                                    <h5 class="card-subtitle" style="text-align: left">{{ filterSubtitle }}</h5>
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
                        <apexchart type="polarArea" :options="computedPolarAreaOptions" :series="polarAreaSeries"
                            height="400" />
                    </div>
                </v-card-text>
            </VCard>
        </v-col>

        <v-col cols="12" sm="12" lg="12">
            <v-card elevation="10" style="position: relative;">
                <v-overlay :model-value="exportLoading" class="align-center justify-center" persistent scrim="white"
                    style="opacity: 0.8;">
                    <div class="text-center">
                        <v-progress-circular color="primary" indeterminate size="64"></v-progress-circular>
                        <h4 class="text-primary mt-3">กำลังสร้างไฟล์รายงาน...</h4>
                    </div>
                </v-overlay>

                <v-card-text>
                    <div class="v-row">
                        <div class="v-col-md-8 v-col-12">
                            <div>
                                <h3 class="card-title mb-1">
                                    ตารางสรุปยอดรายเดือน (แยกตามมูลค่า)
                                    <span class="text-subtitle-1 text-grey-darken-1 ml-2">{{ tableUnitSubtitle }}</span>
                                </h3>
                                <h5 class="card-subtitle" style="text-align: left">{{ filterSubtitle }}</h5>
                            </div>
                        </div>
                        <div class="v-col-md-4 v-col-12 text-right">
                            <div class="d-flex justify-end ga-2 v-col-md-12 v-col-lg-12 v-col-12">

                                

                                <!-- <v-btn color="primary" variant="outlined" @click="exportToExcel"
                                    class="v-btn--size-small" :loading="exportLoading">
                                    <v-icon start>mdi-file-excel</v-icon>
                                    Export Excel
                                </v-btn>

                                <v-btn color="primary" variant="outlined" @click="exportToPdf" class="v-btn--size-small"
                                    :loading="exportLoading">
                                    <v-icon start>mdi-file-pdf-box</v-icon>
                                    Export PDF
                                </v-btn> -->

                                <v-btn-group color="#b2d7ef" density="comfortable" rounded="pill" divided>
            <v-btn color="success" @click="exportToExcel">
                <v-icon start>mdi-file-excel</v-icon>
                <div class="text-none font-weight-regular">รายงาน Excel</div>
            </v-btn>
            <v-btn color="error" @click="exportToPdf" :loading="exportLoading">
                    <v-icon start>mdi-file-pdf-box</v-icon>
                <div class="text-none font-weight-regular">รายงาน PDF</div>
            </v-btn>
        </v-btn-group>

                            </div>
                        </div>
                    </div>
                    <br /><br />

                    <div class="v-table v-theme--BLUE_THEME v-table--density-default month-table">
                        <div class="v-table__wrapper" style="overflow-x: auto;">
                            <table id="house-table-to-export">
                                <thead style="background-color: #F5F5F5;">
                                    <tr>
                                        <th class="text-h6" style="min-width: 100px;"></th>
                                        <th v-for="month in displayedMonths" :key="month.value" class="text-p"
                                            style="font-size: 13px; text-align: center;">
                                            {{ month.short }}
                                        </th>
                                        <th class="text-p"
                                            style="font-size: 13px; font-weight: 600; background-color: #FFF3E0; text-align: center;">
                                            รวม
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <template v-for="range in priceRanges" :key="range">

                                        <tr class="month-item" style="background-color: #fcf8ff;">
                                            <td>
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 600; color: #725AF2;">{{ range
                                                    }}</h6>
                                            </td>
                                            <td :colspan="displayedMonths.length + 1"></td>
                                        </tr>

                                        <tr class="month-item" v-show="isRowVisible('จำนวนหลัง')"
                                            :style="getHighlightStyle('จำนวนหลัง')">
                                            <td>
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 400; padding-left: 15px;">
                                                    จำนวนหลัง</h6>
                                            </td>

                                            <td v-for="month in displayedMonths" :key="month.value + '-unit'"
                                                style="text-align: right; vertical-align: middle;">
                                                <div>
                                                    <h6 class="text-p" style="font-size: 12px; font-weight: 400;">{{
                                                        getDetailedValue('unit', month.value, range) }}</h6>
                                                    <span
                                                        v-if="showMoM && getMoMCellData(range, month.value, 'unit') !== null"
                                                        class="text-caption ml-1"
                                                        :class="getPercentageColor(getMoMCellData(range, month.value, 'unit'))"
                                                        style="font-weight: 400; font-size: 9px !important;">
                                                        {{ formatPercentage(getMoMCellData(range, month.value, 'unit'))
                                                        }}
                                                    </span>
                                                </div>
                                            </td>

                                            <td style="background-color: #FFF3E0; text-align: right; vertical-align: middle;"
                                                :style="getHighlightStyle('จำนวนหลัง')">
                                                <div>
                                                    <h6 class="text-p" style="font-size: 12px; font-weight: 600;">{{
                                                        getFormattedHorizontalTotal(range, 'unit') }}</h6>
                                                    <span class="text-caption ml-1"
                                                        :class="getPercentageColor(getRowTotalYoY(range, 'unit'))"
                                                        style="font-weight: 400; font-size: 9px !important;">
                                                        {{ formatPercentage(getRowTotalYoY(range, 'unit')) }}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr class="month-item" v-show="isRowVisible('มูลค่ารวม')"
                                            :style="getHighlightStyle('มูลค่ารวม')">
                                            <td>
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 400; padding-left: 15px;">
                                                    มูลค่ารวม</h6>
                                            </td>

                                            <td v-for="month in displayedMonths" :key="month.value + '-value'"
                                                style="text-align: right; vertical-align: middle;">
                                                <div>
                                                    <h6 class="text-p" style="font-size: 12px; font-weight: 400;">{{
                                                        getDetailedValue('total_value', month.value, range) }}</h6>
                                                    <span
                                                        v-if="showMoM && getMoMCellData(range, month.value, 'total_value') !== null"
                                                        class="text-caption ml-1"
                                                        :class="getPercentageColor(getMoMCellData(range, month.value, 'total_value'))"
                                                        style="font-weight: 400; font-size: 9px !important;">
                                                        {{ formatPercentage(getMoMCellData(range, month.value,
                                                            'total_value')) }}
                                                    </span>
                                                </div>
                                            </td>

                                            <td style="background-color: #FFF3E0; text-align: right; vertical-align: middle;"
                                                :style="getHighlightStyle('มูลค่ารวม')">
                                                <div>
                                                    <h6 class="text-p" style="font-size: 12px; font-weight: 600;">{{
                                                        getFormattedHorizontalTotal(range, 'total_value') }}</h6>
                                                    <span class="text-caption ml-1"
                                                        :class="getPercentageColor(getRowTotalYoY(range, 'total_value'))"
                                                        style="font-weight: 400; font-size: 9px !important;">
                                                        {{ formatPercentage(getRowTotalYoY(range, 'total_value')) }}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr class="month-item" v-show="isRowVisible('พื้นที่ใช้สอย')"
                                            :style="getHighlightStyle('พื้นที่ใช้สอย')">
                                            <td>
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 400; padding-left: 15px;">
                                                    พื้นที่ใช้สอย</h6>
                                            </td>

                                            <td v-for="month in displayedMonths" :key="month.value + '-area'"
                                                style="text-align: right; vertical-align: middle;">
                                                <div>
                                                    <h6 class="text-p" style="font-size: 12px; font-weight: 400;">{{
                                                        getDetailedValue('usable_area', month.value, range) }}</h6>
                                                    <span
                                                        v-if="showMoM && getMoMCellData(range, month.value, 'usable_area') !== null"
                                                        class="text-caption ml-1"
                                                        :class="getPercentageColor(getMoMCellData(range, month.value, 'usable_area'))"
                                                        style="font-weight: 400; font-size: 9px !important;">
                                                        {{ formatPercentage(getMoMCellData(range, month.value,
                                                            'usable_area')) }}
                                                    </span>
                                                </div>
                                            </td>

                                            <td style="background-color: #FFF3E0; text-align: right; vertical-align: middle;"
                                                :style="getHighlightStyle('พื้นที่ใช้สอย')">
                                                <div>
                                                    <h6 class="text-p" style="font-size: 12px; font-weight: 600;">{{
                                                        getFormattedHorizontalTotal(range, 'usable_area') }}</h6>
                                                    <span class="text-caption ml-1"
                                                        :class="getPercentageColor(getRowTotalYoY(range, 'usable_area'))"
                                                        style="font-weight: 400; font-size: 9px !important;">
                                                        {{ formatPercentage(getRowTotalYoY(range, 'usable_area')) }}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr class="month-item" v-show="isRowVisible('ราคาเฉลี่ย/ตร.ม.')"
                                            :style="getHighlightStyle('ราคาเฉลี่ย/ตร.ม.')">
                                            <td>
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 400; padding-left: 15px;">
                                                    ราคาเฉลี่ย/ตร.ม.</h6>
                                            </td>

                                            <td v-for="month in displayedMonths" :key="month.value + '-avg'"
                                                style="text-align: right; vertical-align: middle;">
                                                <div>
                                                    <h6 class="text-p" style="font-size: 12px; font-weight: 400;">{{
                                                        getDetailedValue('price_per_sqm', month.value, range) }}</h6>
                                                    <span
                                                        v-if="showMoM && getMoMCellData(range, month.value, 'price_per_sqm') !== null"
                                                        class="text-caption ml-1"
                                                        :class="getPercentageColor(getMoMCellData(range, month.value, 'price_per_sqm'))"
                                                        style="font-weight: 400; font-size: 9px !important;">
                                                        {{ formatPercentage(getMoMCellData(range, month.value,
                                                            'price_per_sqm')) }}
                                                    </span>
                                                </div>
                                            </td>

                                            <td style="background-color: #FFF3E0; text-align: right; vertical-align: middle;"
                                                :style="getHighlightStyle('ราคาเฉลี่ย/ตร.ม.')">
                                                <div>
                                                    <h6 class="text-p" style="font-size: 12px; font-weight: 600;">{{
                                                        getFormattedHorizontalTotal(range, 'price_per_sqm') }}</h6>
                                                    <span class="text-caption ml-1"
                                                        :class="getPercentageColor(getRowTotalYoY(range, 'price_per_sqm'))"
                                                        style="font-weight: 400; font-size: 9px !important;">
                                                        {{ formatPercentage(getRowTotalYoY(range, 'price_per_sqm')) }}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>

                                    <tr class="month-item" style="background-color: #fcf8ff;"
                                        v-show="isRowVisible('จำนวนหลัง')" :style="getHighlightStyle('จำนวนหลัง')">
                                        <td>
                                            <h6 class="text-p"
                                                style="font-size: 13px; font-weight: 600; color: #F8285A;">
                                                จำนวนหลัง
                                                (รวม)</h6>
                                        </td>

                                        <td v-for="month in displayedMonths" :key="month.value + '-total-unit'"
                                            style="text-align: right; vertical-align: middle;">
                                            <div>
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 600; color: #F8285A;">{{
                                                        getFormattedMonthTotal(month.value, 'unit') }}</h6>
                                                <span v-if="showMoM && getMonthTotalMoM(month.value, 'unit') !== null"
                                                    class="text-caption ml-1"
                                                    :class="getPercentageColor(getMonthTotalMoM(month.value, 'unit'))"
                                                    style="font-weight: 400; font-size: 9px !important;">
                                                    {{ formatPercentage(getMonthTotalMoM(month.value, 'unit')) }}
                                                </span>
                                            </div>
                                        </td>

                                        <td style="background-color: #FFF3E0; text-align: right; vertical-align: middle;"
                                            :style="getHighlightStyle('จำนวนหลัง')">
                                            <div>
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 800; color: #F8285A;">{{
                                                        getFormattedGrandTotal('unit') }}</h6>
                                                <span class="text-caption ml-1"
                                                    :class="getPercentageColor(getGrandTotalYoY('unit'))"
                                                    style="font-weight: 600; color: #F8285A; font-size: 9px !important;">
                                                    {{ formatPercentage(getGrandTotalYoY('unit')) }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr class="month-item" style="background-color: #fcf8ff;"
                                        v-show="isRowVisible('มูลค่ารวม')" :style="getHighlightStyle('มูลค่ารวม')">
                                        <td>
                                            <h6 class="text-p"
                                                style="font-size: 13px; font-weight: 600; color: #F8285A;">
                                                มูลค่ารวม
                                                (รวม)</h6>
                                        </td>

                                        <td v-for="month in displayedMonths" :key="month.value + '-total-value'"
                                            style="text-align: right; vertical-align: middle;">
                                            <div>
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 600; color: #F8285A;">{{
                                                        getFormattedMonthTotal(month.value, 'total_value') }}</h6>
                                                <span
                                                    v-if="showMoM && getMonthTotalMoM(month.value, 'total_value') !== null"
                                                    class="text-caption ml-1"
                                                    :class="getPercentageColor(getMonthTotalMoM(month.value, 'total_value'))"
                                                    style="font-weight: 400; font-size: 9px !important;">
                                                    {{ formatPercentage(getMonthTotalMoM(month.value, 'total_value'))
                                                    }}
                                                </span>
                                            </div>
                                        </td>

                                        <td style="background-color: #FFF3E0; text-align: right; vertical-align: middle;"
                                            :style="getHighlightStyle('มูลค่ารวม')">
                                            <div>
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 800; color: #F8285A;">{{
                                                        getFormattedGrandTotal('total_value') }}</h6>
                                                <span class="text-caption ml-1"
                                                    :class="getPercentageColor(getGrandTotalYoY('total_value'))"
                                                    style="font-weight: 600; color: #F8285A; font-size: 9px !important;">
                                                    {{ formatPercentage(getGrandTotalYoY('total_value')) }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr class="month-item" style="background-color: #fcf8ff;"
                                        v-show="isRowVisible('พื้นที่ใช้สอย')"
                                        :style="getHighlightStyle('พื้นที่ใช้สอย')">
                                        <td>
                                            <h6 class="text-p"
                                                style="font-size: 13px; font-weight: 600; color: #F8285A;">
                                                พื้นที่ใช้สอย
                                                (รวม)</h6>
                                        </td>

                                        <td v-for="month in displayedMonths" :key="month.value + '-total-area'"
                                            style="text-align: right; vertical-align: middle;">
                                            <div>
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 600; color: #F8285A;">{{
                                                        getFormattedMonthTotal(month.value, 'usable_area') }}</h6>
                                                <span
                                                    v-if="showMoM && getMonthTotalMoM(month.value, 'usable_area') !== null"
                                                    class="text-caption ml-1"
                                                    :class="getPercentageColor(getMonthTotalMoM(month.value, 'usable_area'))"
                                                    style="font-weight: 400; font-size: 9px !important;">
                                                    {{ formatPercentage(getMonthTotalMoM(month.value, 'usable_area'))
                                                    }}
                                                </span>
                                            </div>
                                        </td>

                                        <td style="background-color: #FFF3E0; text-align: right; vertical-align: middle;"
                                            :style="getHighlightStyle('พื้นที่ใช้สอย')">
                                            <div>
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 800; color: #F8285A;">{{
                                                        getFormattedGrandTotal('usable_area') }}</h6>
                                                <span class="text-caption ml-1"
                                                    :class="getPercentageColor(getGrandTotalYoY('usable_area'))"
                                                    style="font-weight: 600; color: #F8285A; font-size: 9px !important;">
                                                    {{ formatPercentage(getGrandTotalYoY('usable_area')) }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr class="month-item" style="background-color: #fcf8ff;"
                                        v-show="isRowVisible('ราคาเฉลี่ย/ตร.ม.')"
                                        :style="getHighlightStyle('ราคาเฉลี่ย/ตร.ม.')">
                                        <td>
                                            <h6 class="text-p"
                                                style="font-size: 13px; font-weight: 600; color: #F8285A;">
                                                ราคาเฉลี่ย/ตร.ม. (รวม)</h6>
                                        </td>

                                        <td v-for="month in displayedMonths" :key="month.value + '-total-avg'"
                                            style="text-align: right; vertical-align: middle;">
                                            <div>
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 600; color: #F8285A;">{{
                                                        getFormattedMonthTotal(month.value, 'price_per_sqm') }}</h6>
                                                <span
                                                    v-if="showMoM && getMonthTotalMoM(month.value, 'price_per_sqm') !== null"
                                                    class="text-caption ml-1"
                                                    :class="getPercentageColor(getMonthTotalMoM(month.value, 'price_per_sqm'))"
                                                    style="font-weight: 400; font-size: 9px !important;">
                                                    {{ formatPercentage(getMonthTotalMoM(month.value, 'price_per_sqm'))
                                                    }}
                                                </span>
                                            </div>
                                        </td>

                                        <td style="background-color: #FFF3E0; text-align: right; vertical-align: middle;"
                                            :style="getHighlightStyle('ราคาเฉลี่ย/ตร.ม.')">
                                            <div>
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 800; color: #F8285A;">{{
                                                        getFormattedGrandTotal('price_per_sqm') }}</h6>
                                                <span class="text-caption ml-1"
                                                    :class="getPercentageColor(getGrandTotalYoY('price_per_sqm'))"
                                                    style="font-weight: 600; color: #F8285A; font-size: 9px !important;">
                                                    {{ formatPercentage(getGrandTotalYoY('price_per_sqm')) }}
                                                </span>
                                            </div>
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


    </v-row>
</template>

<style scoped>
.text-subtitle-1 {
    font-size: 14px;
}

/* (CSS จากไฟล์ตัวอย่าง) */
.text-h6 {
    font-size: 18px;
}

.month-item td,
.month-item th {
    padding: 8px !important;
    border-bottom: 1px solid #eee;
    /* ♻️ [เพิ่มใหม่] จัดให้ % ที่ขึ้นบรรทัดใหม่ อยู่ชิดขวาเหมือนค่าหลัก */
    vertical-align: top;
}

/* ✅ [เพิ่มใหม่] CSS สำหรับการ์ดที่คลิกได้ */
.v-card[style*="cursor: pointer"] {
    transition: transform 0.2s ease-in-out, background-color 0.2s ease-in-out;
}

/* 1. เมื่อ "Hover" หรือ "ถูกคลิก" (Active) -> เปลี่ยน "พื้นหลัง" */
.v-card[style*="cursor: pointer"]:hover,
.v-card.card-is-active {
    background-color: #E3F2FD !important;
    /* สีฟ้าอ่อน */
    transform: translateY(-2px);
}

/* 2. เมื่อ "Hover" หรือ "Active" -> เปลี่ยน "สีข้อความ" */
.v-card[style*="cursor: pointer"]:hover .text-h4,
.v-card[style*="cursor: pointer"]:hover .textSecondary,
.v-card.card-is-active .text-h4,
.v-card.card-is-active .textSecondary {
    color: #1E88E5 !important;
    /* สีฟ้าเข้ม */
}

/* ✅ [เพิ่มใหม่] CSS สำหรับปุ่ม TimeRange ที่อาจจะขึ้นบรรทัดใหม่ */
.flex-wrap {
    flex-wrap: wrap;
    height: auto !important;
    /* override v-btn-toggle height */
}
</style>