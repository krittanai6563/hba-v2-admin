<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import * as XLSX from 'xlsx';
import ExcelJS from 'exceljs';
import type { BorderStyle, Cell } from 'exceljs';

// --- (ส่วนฟิลเตอร์เหมือนเดิม) ---
const jsDate = new Date();
const currentJsYear = jsDate.getFullYear();
const currentJsMonth = jsDate.getMonth() + 1; // (1-12)



const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user';

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


const priceRanges = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป'];
const dataTypes = ['unit', 'total_value', 'usable_area', 'price_per_sqm'];
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


const polarAreaOptions = ref({
    // ... (โค้ด options ของกราฟเหมือนเดิม) ...
    chart: { type: 'polarArea', fontFamily: 'inherit', foreColor: '#6c757d' },
    labels: priceRanges,
    legend: { position: 'bottom', horizontalAlign: 'center' },
    stroke: { colors: ['#fff'] },
    fill: { opacity: 0.8 },
    responsive: [{ breakpoint: 480, options: { chart: { width: 200 }, legend: { position: 'bottom' } } }],
    tooltip: { theme: 'dark', y: { formatter: (val: number) => val.toLocaleString('th-TH') + " บาท" } },
    dataLabels: {
        enabled: true,
        formatter: (val: number, opts: any) => {
            let percentageText = '0.00%';
            if (!isNaN(val)) percentageText = (Number(val) || 0).toFixed(2) + '%';
            if (!summaryData.value || !summaryData.value.total_value) return percentageText;
            const rangeKey = priceRanges[opts.dataPointIndex];
            const rawValue = summaryData.value.total_value[rangeKey];
            if (rawValue === undefined || rawValue === null) return percentageText;
            const rawValueText = (Number(rawValue) || 0).toLocaleString('th-TH');
            return [percentageText, `(${rawValueText})`];
        },
        style: { fontSize: '10px' },
        dropShadow: { enabled: false }
    },
    noData: { text: 'ไม่พบข้อมูลสำหรับช่วงที่เลือก', align: 'center', verticalAlign: 'middle', offsetY: 0, style: { color: '#6c757d', fontSize: '14px', fontFamily: 'inherit' } },
});

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
                formatter: (val: number) => val.toLocaleString('th-TH') + tooltipSuffix 
            } 
        },

        // --- (Dynamic Data Labels) ---
        dataLabels: {
            enabled: true,
            formatter: (val: number, opts: any) => {
                let percentageText = '0.00%';
                if (!isNaN(val)) percentageText = (Number(val) || 0).toFixed(2) + '%';
                
                if (!summaryData.value || !summaryData.value[selectedMetricKey]) return percentageText;

                const rangeKey = priceRanges[opts.dataPointIndex];
                
                // @ts-ignore
                const rawValue = summaryData.value[selectedMetricKey][rangeKey];
                
                if (rawValue === undefined || rawValue === null) return percentageText;
                
                const rawValueText = (Number(rawValue) || 0).toLocaleString('th-TH');
                return [percentageText, `(${rawValueText})`];
            },
            style: { fontSize: '10px' },
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
    return 'สัดส่วนมูลค่ารวม (Total Value) ตามช่วงราคา'; // (ค่าเริ่มต้น)
});

// (5) ฟังก์ชันดึงข้อมูล "หลัก" (สำหรับกราฟ/การ์ด/Export)
const fetchSummary = async () => {
    if (!userId && userRole !== 'admin') return;
    if (selectedMonths.value.length === 0 || !selectedYear.value) {
        summaryData.value = { unit: {}, total_value: {}, usable_area: {}, price_per_sqm: {} };
        // polarAreaSeries.value = []; 
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

                    // ✅ [แก้ไข] ป้องกัน NaN
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
                aggregatedData.price_per_sqm[range] = Math.round(aggregatedData.total_value[range] / aggregatedData.usable_area[range]);
            } else { aggregatedData.price_per_sqm[range] = 0; }
        }
        if (totalAreaForAvg > 0) {
            aggregatedData.price_per_sqm['total'] = Math.round(totalValueForAvg / totalAreaForAvg);
        } else { aggregatedData.price_per_sqm['total'] = 0; }
        summaryData.value = aggregatedData; 
        console.log('✅ ข้อมูลที่ประมวลผลแล้ว (สำหรับกราฟ/การ์ด):', aggregatedData);
        // const newPolarSeries = priceRanges.map(range => aggregatedData.total_value[range] || 0);
        // const totalSum = newPolarSeries.reduce((a, b) => a + b, 0);
        // polarAreaSeries.value = totalSum > 0 ? newPolarSeries : [];
    } catch (err) { console.error('❌ Error fetching summary:', err); }
};

// (6) ฟังก์ชันสำหรับดึงข้อมูลตาราง "แบบละเอียด"
const fetchDetailedTableData = async () => {
    if (!userId && userRole !== 'admin') return;
    const newData: Record<number, Record<string, PriceRangeMetrics>> = {};
    const monthsToFetch = [...selectedMonths.value];
    if (monthsToFetch.length === 0 || !selectedYear.value) {
        detailedTableData.value = newData; 
        return;
    }
    for (const month of monthsToFetch) {
        newData[month] = {};
        for (const range of priceRanges) {
            newData[month][range] = { unit: 0, total_value: 0, usable_area: 0, price_per_sqm: 0 };
        }
        try {
            const res = await fetch('https://uat.hba-sales.org/backend/get_contract_summary_main.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    user_id: userId,
                    buddhist_year: selectedYear.value,
                    months: [month], // ⭐️ ส่งข้อมูลทีละเดือน
                    role: userRole
                })
            });
            if (!res.ok) { console.error(`Error fetching data for month ${month}: Status ${res.status}`); continue; }
            const data = await res.json(); 
            const regions = Object.keys(data); 
            for (const region of regions) {
                for (const range of priceRanges) { 
                    if (data[region] && data[region][range]) {
                        const metrics = data[region][range];
                        
                        // ✅ [แก้ไข] ป้องกัน NaN
                        newData[month][range].unit += (Number(metrics.unit) || 0);
                        newData[month][range].total_value += (Number(metrics.value) || 0); 
                        newData[month][range].usable_area += (Number(metrics.area) || 0);   
                    }
                }
            }
            for (const range of priceRanges) {
                const monthRangeData = newData[month][range];
                if (monthRangeData.usable_area > 0) {
                    monthRangeData.price_per_sqm = Math.round(monthRangeData.total_value / monthRangeData.usable_area);
                } else { monthRangeData.price_per_sqm = 0; }
            }
        } catch (err) { console.error(`❌ Error fetching detailed summary for month ${month}:`, err); }
    }
    detailedTableData.value = newData;
    console.log('✅ ข้อมูลตารางแบบละเอียด (ประมวลผลแล้ว):', newData);
};

// (7) ฟังก์ชัน helper สำหรับตารางแบบละเอียด (เหมือน V3)
const getDetailedValue = (type: keyof PriceRangeMetrics, monthValue: number, range: string) => {
    const value = detailedTableData.value?.[monthValue]?.[range]?.[type] || 0;
    return value.toLocaleString('th-TH');
};


watch(selectedQuarter, (newQuarter) => {

    if (isUpdatingFromMonths.value) {
        isUpdatingFromMonths.value = false; // เอาธงลง
        return; // หยุดทำงาน ป้องกัน Loop
    }

    // (โค้ดเดิมทำงานต่อตามปกติ)
    if (newQuarter === 'all') updateToAllMonths();
    else if (newQuarter === 'Q1') selectedMonths.value = [1, 2, 3];
    else if (newQuarter === 'Q2') selectedMonths.value = [4, 5, 6];
    else if (newQuarter === 'Q3') selectedMonths.value = [7, 8, 9];
    else if (newQuarter === 'Q4') selectedMonths.value = [10, 11, 12];
});
watch(selectedYear, () => {
    // ⭐️ [เพิ่มใหม่] ดึง "เดือนที่ถูกต้อง" ของปีที่เพิ่งเลือก
    const validMonths = monthOptions.value.map(m => m.value);
    
    // ⭐️ [เพิ่มใหม่] ล้างเดือนที่ไม่มีอยู่จริง (เช่น ธ.ค. ของปีปัจจุบัน) ออกจากค่าที่เลือกไว้
    selectedMonths.value = selectedMonths.value.filter(m => validMonths.includes(m));

    // (โค้ดเดิม)
    if (selectedQuarter.value === 'all') {
        updateToAllMonths();
    } else {
        // (ถ้าไม่ได้เลือก 'all' เช่น เลือก Q4 ไว้)
        // ค่า selectedMonths จะถูกกรองเหลือแค่เดือนที่ถูกต้อง (เช่น [10, 11])
        // แล้วดึงข้อมูลใหม่ตามค่าที่เหลือ
        fetchSummary(); 
        fetchDetailedTableData(); 
    }
});
watch(selectedMonths, () => {
    const sortedMonths = [...selectedMonths.value].sort((a, b) => a - b).join(',');
    if (sortedMonths === '1,2,3') selectedQuarter.value = 'Q1';
    else if (sortedMonths === '4,5,6') selectedQuarter.value = 'Q2';
    else if (sortedMonths === '7,8,9') selectedQuarter.value = 'Q3';
    else if (sortedMonths === '10,11,12') selectedQuarter.value = 'Q4';
    // ... (else if ของ Q4) ...
    else {
        // (ส่วนนี้คือ "ไม่ใช่ไตรมาสใดเลย")
        const allMonthsCurrentYear = allMonthItems.map(m => m.value).slice(0, currentJsMonth).join(',');
        const allMonthsPastYear = allMonthItems.map(m => m.value).join(',');

        // ตรวจสอบว่าเป็น "ทุกเดือน" หรือไม่
        if (sortedMonths === allMonthsCurrentYear || sortedMonths === allMonthsPastYear) {
            if (selectedQuarter.value !== 'all') {
                isUpdatingFromMonths.value = true; // 👈 [แก้ไข] ยกธง
                selectedQuarter.value = 'all';
            }
        } 
        // ถ้า "ไม่ใช่ทุกเดือน" และ "ไม่ใช่ไตรมาส" = "เลือกเอง"
        else if (selectedQuarter.value !== 'all') { 
            isUpdatingFromMonths.value = true; // 👈 [แก้ไข] ยกธง
            selectedQuarter.value = 'all';
        }
    }
// ...
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

// (9) Computed สำหรับหัวตารางรายเดือน (เหมือน V3)
const displayedMonths = computed(() => {
    // ⭐️ แสดงผลตามค่าใน selectedMonths.value ที่กรองมาดีแล้วเท่านั้น
    // และเรียงลำดับให้ถูกต้อง
    const selectedMonthValues = selectedMonths.value;
    return allMonthItems
        .filter(m => selectedMonthValues.includes(m.value))
        .sort((a, b) => a.value - b.value);
});


// (10) ฟังก์ชัน getValue (สำหรับกราฟ/การ์ด) (เหมือน V3)
const getValue = (type: string, range: string) => {
    // @ts-ignore
    return summaryData.value?.[type]?.[range] || 0;
};
// (10.1) Export Excel (เหมือน V3)
const exportToExcel = async () => {
    // (โค้ด exportToExcel เหมือนเดิม...
    // ... มันจะ export ข้อมูลแบบ Price Range รวม ซึ่งสอดคล้องกับกราฟ)
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Contract Summary');
    worksheet.addRow([`สรุปข้อมูลรายงานแบ่งตามมูลค่า ประจำปี ${selectedYear.value}`]);
    worksheet.getRow(1).font = { bold: true, size: 14 };
    worksheet.getRow(1).alignment = { horizontal: 'center' };
    worksheet.mergeCells('A1:E1');
    const headerRow = ['Price Range', 'จำนวนหลัง', 'มูลค่ารวม', 'พื้นที่ใช้สอย', 'ราคาเฉลี่ย/ตร.ม.'];
    const headerRowFormatted = worksheet.addRow(headerRow);
    headerRowFormatted.font = { bold: true };
    headerRowFormatted.alignment = { horizontal: 'center' };
    headerRowFormatted.eachCell((cell: Cell) => {
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'D3D3D3' } };
        cell.border = { top: { style: 'thin' as BorderStyle }, left: { style: 'thin' as BorderStyle }, right: { style: 'thin' as BorderStyle }, bottom: { style: 'thin' as BorderStyle } };
    });
    priceRanges.forEach((range) => {
        const row = [
            range,
            getValue('unit', range), getValue('total_value', range),
            getValue('usable_area', range), getValue('price_per_sqm', range)
        ];
        const dataRow = worksheet.addRow(row);
        dataRow.getCell(2).numFmt = '#,##0'; dataRow.getCell(3).numFmt = '#,##0.00';
        dataRow.getCell(4).numFmt = '#,##0.00'; dataRow.getCell(5).numFmt = '#,##0.00';
        dataRow.eachCell((cell: Cell) => {
            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'D9EAF7' } };
            cell.border = { top: { style: 'thin' as BorderStyle }, left: { style: 'thin' as BorderStyle }, right: { style: 'thin' as BorderStyle }, bottom: { style: 'thin' as BorderStyle } };
        });
    });
    worksheet.columns = [
        { header: 'Price Range', key: 'price_range', width: 25 }, { header: 'จำนวนหลัง', key: 'unit', width: 15 },
        { header: 'มูลค่ารวม', key: 'total_value', width: 20 }, { header: 'พื้นที่ใช้สอย', key: 'usable_area', width: 20 },
        { header: 'ราคาเฉลี่ย/ตร.ม.', key: 'price_per_sqm', width: 20 }
    ];
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheet.sheet' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'contract_summary_report.xlsx'; a.click();
    window.URL.revokeObjectURL(url);
};


// ----------------------------------------------------------------
// ✅ [เพิ่มใหม่] (11) ฟังก์ชันสำหรับคำนวณ "ผลรวม" (Totals)
// ----------------------------------------------------------------

// (ฟังก์ชันนี้เหมือน getDetailedValue แต่คืนค่าเป็นตัวเลข)
function getNumericDetailedValue(type: keyof PriceRangeMetrics, monthValue: number, range: string): number {
    return detailedTableData.value?.[monthValue]?.[range]?.[type] || 0;
}

// 1. ผลรวมแนวนอน (รวมทุกเดือน)
function getHorizontalTotal(priceRange: string, field: keyof PriceRangeMetrics): number {
    // วนลูปทุกเดือนที่แสดง
    return displayedMonths.value.reduce((total, month) => {
        return total + getNumericDetailedValue(field, month.value, priceRange);
    }, 0);
}

// 2. ผลรวมแนวตั้ง (รวมทุกช่วงราคา)
function getMonthTotal(monthValue: number, field: keyof PriceRangeMetrics): number {
    // วนลูปทุกช่วงราคา
    return priceRanges.reduce((total, range) => {
        return total + getNumericDetailedValue(field, monthValue, range);
    }, 0);
}

// 3. ผลรวมทั้งหมด (รวมทุกเดือนและทุกช่วงราคา)
function getGrandTotal(field: keyof PriceRangeMetrics): number {
    // วนลูปทุกเดือนที่แสดง และรวมยอดของแต่ละเดือน
    return displayedMonths.value.reduce((total, month) => {
        return total + getMonthTotal(month.value, field);
    }, 0);
}

// 4. ฟังก์ชันสำหรับจัดรูปแบบ (จัดการ 'price_per_sqm' ที่ต้องคำนวณใหม่)

// (ผลรวมแนวนอน)
function getFormattedHorizontalTotal(priceRange: string, field: keyof PriceRangeMetrics): string {
    if (field === 'price_per_sqm') {
        const totalValue = getHorizontalTotal(priceRange, 'total_value');
        const totalArea = getHorizontalTotal(priceRange, 'usable_area');
        const avg = totalArea > 0 ? totalValue / totalArea : 0;
        // 🐞 [แก้ไขเล็กน้อย] ให้แสดง 2 ทศนิยมเสมอสำหรับ 'price_per_sqm'
        return avg.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    const total = getHorizontalTotal(priceRange, field);
    return total.toLocaleString('th-TH');
}

// (ผลรวมแนวตั้ง)
function getFormattedMonthTotal(monthValue: number, field: keyof PriceRangeMetrics): string {
    if (field === 'price_per_sqm') {
        const totalValue = getMonthTotal(monthValue, 'total_value');
        const totalArea = getMonthTotal(monthValue, 'usable_area');
        const avg = totalArea > 0 ? totalValue / totalArea : 0;
        // 🐞 [แก้ไขเล็กน้อย] ให้แสดง 2 ทศนิยมเสมอสำหรับ 'price_per_sqm'
        return avg.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    const total = getMonthTotal(monthValue, field);
    return total.toLocaleString('th-TH');
}

// (ผลรวมทั้งหมด)
function getFormattedGrandTotal(field: keyof PriceRangeMetrics): string {
    if (field === 'price_per_sqm') {
        const totalValue = getGrandTotal('total_value');
        const totalArea = getGrandTotal('usable_area');
        const avg = totalArea > 0 ? totalValue / totalArea : 0;
        // 🐞 [แก้ไขเล็กน้อย] ให้แสดง 2 ทศนิยมเสมอสำหรับ 'price_per_sqm'
        return avg.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    const total = getGrandTotal(field);
    return total.toLocaleString('th-TH');
}

// (วางโค้ดนี้ใน <script setup lang="ts">)

const filterSubtitle = computed(() => {
    // 1. เริ่มต้นด้วยปีที่เลือก
    const yearText = `ประจำปี ${selectedYear.value}`;

    // 2. สร้าง key จากเดือนที่เลือก (สำหรับใช้เปรียบเทียบ)
    const sortedMonthsKey = [...selectedMonths.value].sort((a, b) => a - b).join(',');

    // 3. ตรวจสอบไตรมาส (✅ อัปเดต: ใช้ชื่อเดือนเต็ม)
    if (sortedMonthsKey === '1,2,3') return `(${yearText} - ไตรมาส 1 (มกราคม - มีนาคม))`;
    if (sortedMonthsKey === '4,5,6') return `(${yearText} - ไตรมาส 2 (เมษายน - มิถุนายน))`;
    if (sortedMonthsKey === '7,8,9') return `(${yearText} - ไตรมาส 3 (กรกฎาคม - กันยายน))`;
    if (sortedMonthsKey === '10,11,12') return `(${yearText} - ไตรมาส 4 (ตุลาคม - ธันวาคม))`;

    // 4. ตรวจสอบ "ทุกเดือน" (All) (คงเดิม)
    const yearAD = selectedYear.value - 543;
    const allMonthsCurrentYear = allMonthItems.map(m => m.value).slice(0, currentJsMonth).join(',');
    const allMonthsPastYear = allMonthItems.map(m => m.value).join(',');

    if (sortedMonthsKey === allMonthsCurrentYear || sortedMonthsKey === allMonthsPastYear) {
        // ใช้ title จาก 'all' ใน quarterOptions
        const allOption = quarterOptions.value.find(q => q.value === 'all');
        return `(${yearText} - ${allOption ? allOption.title : 'ทุกเดือน'})`;
    }

    // 5. กรณีเลือกเอง (Custom Selection) (✅ อัปเดต: แสดงชื่อเต็ม และ เดือนเริ่มต้น - สิ้นสุด)
    if (selectedMonths.value.length > 0) {
        
        // 1. เรียงลำดับเดือนที่เลือก
        const sortedMonthValues = [...selectedMonths.value].sort((a, b) => a - b);
        
        // 2. หาเดือนแรกสุด และ เดือนท้ายสุด
        const firstMonthValue = sortedMonthValues[0];
        const lastMonthValue = sortedMonthValues[sortedMonthValues.length - 1];

        // 3. ค้นหาชื่อเต็มจาก allMonthItems
        const firstMonth = allMonthItems.find(m => m.value === firstMonthValue);
        const lastMonth = allMonthItems.find(m => m.value === lastMonthValue);

        // 4. ตรวจสอบว่าหาเจอ
        if (!firstMonth || !lastMonth) {
             return `(${yearText} - กำลังเลือกเดือน...)`; // ข้อความสำรอง
        }

        // 5. แสดงผล
        if (firstMonthValue === lastMonthValue) {
            // กรณีเลือกแค่เดือนเดียว
            return `(${yearText} - เดือน ${firstMonth.title})`;
        } else {
            // กรณีเลือกหลายเดือน (จะแสดงเดือนแรกสุดและท้ายสุด)
            return `(${yearText} - เดือน ${firstMonth.title} - ${lastMonth.title})`;
        }
    }

    // 6. กรณีไม่ได้เลือกเลย
    return `(${yearText} - ยังไม่ได้เลือกเดือน)`;
});


// ----------------------------------------------------------------
// ✅ [เพิ่มใหม่] (12) State และ Functions สำหรับการไฮไลต์การ์ดและตาราง
// (เหมือนใน Shadow.vue)
// ----------------------------------------------------------------

// 1. Labels สำหรับการ์ด (ควรตรงกับ v-for ใน template)
const cardLabels = ['จำนวนหลัง', 'มูลค่ารวม', 'พื้นที่ใช้สอย', 'ราคาเฉลี่ย/ตร.ม.'] as const;

// 2. State สำหรับจำค่าที่เลือก
const selectedHighlight = ref<(typeof cardLabels)[number] | null>(null);

// 3. ฟังก์ชันสำหรับคลิกการ์ด
function highlightRow(label: (typeof cardLabels)[number]) {
  if (selectedHighlight.value === label) {
    selectedHighlight.value = null; // คลิกซ้ำเพื่อยกเลิก
  } else {
    selectedHighlight.value = label;
  }
}

// (4) กราฟ Polar Area (♻️ [แก้ไข] เปลี่ยนเป็น computed)
const polarAreaSeries = computed(() => {
    // 1. ตรวจสอบว่ากำลังเลือก metric ไหน (ค่าเริ่มต้นคือ 'total_value')
    const metricKey = 
        (selectedHighlight.value === 'จำนวนหลัง') ? 'unit' :
        (selectedHighlight.value === 'พื้นที่ใช้สอย') ? 'usable_area' :
        (selectedHighlight.value === 'ราคาเฉลี่ย/ตร.ม.') ? 'price_per_sqm' :
        'total_value'; // (ค่าเริ่มต้น)

    // 2. ถ้าไม่มีข้อมูล ให้คืนค่าว่าง
    if (!summaryData.value[metricKey]) return [];

    // 3. สร้าง Series ใหม่จากข้อมูลที่เลือก
    // @ts-ignore
    const newSeries = priceRanges.map(range => summaryData.value[metricKey][range] || 0);
    
    const totalSum = newSeries.reduce((a, b) => a + b, 0);
    
    // 4. คืนค่า series (ถ้าผลรวมเป็น 0 ก็คืนค่าว่างเพื่อให้แสดง "No Data")
    return totalSum > 0 ? newSeries : [];
});

// 4. ฟังก์ชันสำหรับ v-show ในตาราง
function isRowVisible(label: string): boolean {
  // ถ้าไม่ได้เลือกอะไร (null) ให้แสดงทุกแถว
  if (selectedHighlight.value === null) {
    return true;
  }
  // ถ้ามีปุ่มถูกเลือก ให้แสดงเฉพาะแถวที่ตรงกับปุ่มนั้น
  return selectedHighlight.value === label;
}

// 5. ฟังก์ชันสำหรับ :style ในตาราง
function getHighlightStyle(label: string) {
  if (selectedHighlight.value !== label) return null;

  // สีเดียวกับใน Shadow.vue
  if (label === 'จำนวนหลัง') return { backgroundColor: '#E3F2FD' }; 
  if (label === 'มูลค่ารวม') return { backgroundColor: '#EDE7F6' }; 
  if (label === 'พื้นที่ใช้สอย') return { backgroundColor: '#FFEBEE' }; 
  if (label === 'ราคาเฉลี่ย/ตร.ม.') return { backgroundColor: '#FFF8E1' }; 

  return null;
}

// 6. (Bonus) Computed สำหรับเปลี่ยนหน่วยของตาราง
const tableUnitSubtitle = computed(() => {
  const selected = selectedHighlight.value;
  if (selected === 'จำนวนหลัง') return '(หน่วย : หลัง)';
  if (selected === 'มูลค่ารวม') return '(หน่วย : บาท)'; // หน้านี้แสดงค่าเต็ม
  if (selected === 'พื้นที่ใช้สอย') return '(หน่วย : ตร.ม.)';
  if (selected === 'ราคาเฉลี่ย/ตร.ม.') return '(หน่วย : บาท / ตร.ม.)';
  return ''; // ค่าเริ่มต้น (ไม่แสดงหน่วย)
});

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
                                <li class="v-breadcrumbs-item" text="Home"><a class="v-breadcrumbs-item--link" href="#"><p>หน้าแรก</p></a></li>
                                <li class="v-breadcrumbs-divider"><i class="mdi-chevron-right mdi v-icon notranslate v-theme--BLUE_THEME" aria-hidden="true" style="font-size: 15px; height: 15px; width: 15px"></i></li>
                                <li class="v-breadcrumbs-item" text="Dashboard"><a class="v-breadcrumbs-item--link" href="#"><p>รายงานยอดเซ็นสัญญาแบ่งตามมูลค่าบ้าน</p></a></li>
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
                        <v-col cols="12" sm="4" md="4"><v-select v-model="selectedYear" :items="yearOptions" label="ปี (พ.ศ.)" density="compact" variant="outlined" hide-details /></v-col>
                        <v-col cols="12" sm="4" md="4"><v-select v-model="selectedQuarter" :items="quarterOptions" item-title="title" item-value="value" label="ไตรมาส" density="compact" variant="outlined" hide-details /></v-col>
                        <v-col cols="12" sm="4" md="4"><v-select v-model="selectedMonths" :items="monthOptions" item-title="title" item-value="value" label="เดือน (เลือกได้หลายเดือน)" multiple chips closable-chips density="compact" variant="outlined" hide-details /></v-col>
                    </v-row>
                </v-card-text>
            </v-card>
        </v-col>
 <v-col cols="12" sm="12" lg="12">
            <div class="v-row">
                <div v-for="(label, index) in cardLabels" :key="index" class="v-col-sm-6 v-col-lg-3 v-col-12 py-0 mb-3">
                    
                    <div class="v-card v-theme--BLUE_THEME v-card--density-default elevation-10 rounded-md v-card--variant-elevated"
                        @click="highlightRow(label)"
                        style="cursor: pointer;"
                        hover
                        :class="{ 'card-is-active': selectedHighlight === label }"
                    >
                        <div class="v-card-text pa-5">
                            <div class="d-flex align-center ga-4">
                                <button type="button" class="v-btn v-btn--elevated v-btn--icon v-theme--BLUE_THEME v-btn--density-default v-btn--size-default v-btn--variant-elevated" :class="{ 'bg-primary': label === 'จำนวนหลัง', 'bg-secondary': label === 'มูลค่ารวม', 'bg-error': label === 'พื้นที่ใช้สอย', 'bg-warning': label === 'ราคาเฉลี่ย/ตร.ม.' }" dark>
                                    <svg v-if="label === 'จำนวนหลัง'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 12.204c0-2.289 0-3.433.52-4.381c.518-.949 1.467-1.537 3.364-2.715l2-1.241C9.889 2.622 10.892 2 12 2s2.11.622 4.116 1.867l2 1.241c1.897 1.178 2.846 1.766 3.365 2.715S22 9.915 22 12.203v1.522c0 3.9 0 5.851-1.172 7.063S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.212S2 17.626 2 13.725z" /><path stroke-linecap="round" d="M12 15v3" /></g></svg>
                                     <svg v-else-if="label === 'มูลค่ารวม'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 14c0-3.771 0-5.657 1.172-6.828S6.229 6 10 6h4c3.771 0 5.657 0 6.828 1.172S22 10.229 22 14s0 5.657-1.172 6.828S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.172S2 17.771 2 14Zm14-8c0-1.886 0-2.828-.586-3.414S13.886 2 12 2s-2.828 0-3.414.586S8 4.114 8 6" /><path stroke-linecap="round" d="M12 17.333c1.105 0 2-.746 2-1.666S13.105 14 12 14s-2-.746-2-1.667c0-.92.895-1.666 2-1.666m0 6.666c-1.105 0-2-.746-2-1.666m2 1.666V18m0-8v.667m0 0c1.105 0 2 .746 2 1.666" /></g></svg>
                                     <svg v-else-if="label === 'พื้นที่ใช้สอย'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"><path d="M11 2c-4.055.007-6.178.107-7.536 1.464C2 4.928 2 7.285 2 11.999s0 7.071 1.464 8.536C4.93 21.999 7.286 21.999 12 21.999s7.071 0 8.535-1.464c1.358-1.357 1.457-3.48 1.464-7.536" /><path stroke-linejoin="round" d="m13 11l9-9m0 0h-5.344M22 2v5.344M21 3l-9 9m0 0h4m-4 0V8" /></g></svg>
                                     <svg v-else-if="label === 'ราคาเฉลี่ย/ตร.ม.'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4.979 9.685C2.993 8.891 2 8.494 2 8s.993-.89 2.979-1.685l2.808-1.123C9.773 4.397 10.767 4 12 4s2.227.397 4.213 1.192l2.808 1.123C21.007 7.109 22 7.506 22 8s-.993.89-2.979 1.685l-2.808 1.124C14.227 11.603 13.233 12 12 12s-2.227-.397-4.213-1.191z" /><path d="m5.766 10l-.787.315C2.993 11.109 2 11.507 2 12s.993.89 2.979 1.685l2.808 1.124C9.773 15.603 10.767 16 12 16s2.227-.397 4.213-1.191l2.808-1.124C21.007 12.891 22 12.493 22 12s-.993-.89-2.979-1.685L18.234 10" /><path d="m5.766 14l-.787.315C2.993 15.109 2 15.507 2 16s.993.89 2.979 1.685l2.808 1.124C9.773 19.603 10.767 20 12 20s2.227-.397 4.213-1.192l2.808-1.123C21.007 16.891 22 16.494 22 16c0-.493-.993-.89-2.979-1.685L18.234 14" /></g></svg>
                                </button>
                                <div class="">
                                    <h2 class="text-h4">{{ getValue(dataTypes[index], 'total').toLocaleString('th-TH') }}</h2>
                                    <p class="textSecondary mt-1 text-15">{{ label }}</p>
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
                    </div>
                    <div class="mt-5">
                      <apexchart type="polarArea" :options="computedPolarAreaOptions" :series="polarAreaSeries" height="400" />
                    </div>
                </v-card-text>
            </VCard>
        </v-col>

        <v-col cols="12" sm="12" lg="12">
            <v-card elevation="10">
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
                            <div class="d-flex justify-end v-col-md-12 v-col-lg-12 v-col-12">
                                <v-btn class="text-primary v-btn--size-small" @click="exportToExcel">
                                    <div class="text-none font-weight-regular muted">Export (Price Range)</div>
                                </v-btn>
                            </div>
                        </div>
                    </div>
                    <br /><br />
                    
                    <div class="v-table v-theme--BLUE_THEME v-table--density-default month-table">
                        <div class="v-table__wrapper" style="overflow-x: auto;">
                            <table>
                                <thead style="background-color: #F5F5F5;">
                                <tr>
                                    <th class="text-h6" style="min-width: 150px;"></th>
                                    <th 
                                        v-for="month in displayedMonths" 
                                        :key="month.value" 
                                        class="text-p" 
                                        style="font-size: 13px; text-align: center;"
                                    >
                                       {{ month.short }}
                                    </th>
                                    <th class="text-p" style="font-size: 13px; font-weight: 600; background-color: #FFF3E0; text-align: center;">
                                        รวม
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                <template v-for="range in priceRanges" :key="range">
                                
                                    <tr class="month-item" style="background-color: #fcf8ff;">
                                        <td><h6 class="text-p" style="font-size: 12px; font-weight: 600; color: #725AF2;">{{ range }}</h6></td>
                                        <td :colspan="displayedMonths.length + 1"></td>
                                    </tr>
                        
                                    <tr class="month-item" v-show="isRowVisible('จำนวนหลัง')" :style="getHighlightStyle('จำนวนหลัง')">
                                        <td><h6 class="text-p" style="font-size: 12px; font-weight: 400; padding-left: 15px;">จำนวนหลัง</h6></td>
                                        <td v-for="month in displayedMonths" :key="month.value + '-unit'" style="text-align: right;">
                                            <h6 class="text-p" style="font-size: 13px; font-weight: 400;" >{{ getDetailedValue('unit', month.value, range) }}</h6>
                                        </td>
                                        <td style="background-color: #FFF3E0; text-align: right;" :style="getHighlightStyle('จำนวนหลัง')">
                                            <h6 class="text-p" style="font-size: 13px; font-weight: 600;">{{ getFormattedHorizontalTotal(range, 'unit') }}</h6>
                                        </td>
                                    </tr>
                        
                                    <tr class="month-item" v-show="isRowVisible('มูลค่ารวม')" :style="getHighlightStyle('มูลค่ารวม')">
                                        <td><h6 class="text-p" style="font-size: 12px; font-weight: 400; padding-left: 15px;">มูลค่ารวม</h6></td>
                                        <td v-for="month in displayedMonths" :key="month.value + '-value'" style="text-align: right;">
                                            <h6 class="text-p" style="font-size: 13px; font-weight: 400;">{{ getDetailedValue('total_value', month.value, range) }}</h6>
                                        </td>
                                        <td style="background-color: #FFF3E0; text-align: right;" :style="getHighlightStyle('มูลค่ารวม')">
                                            <h6 class="text-p" style="font-size: 13px; font-weight: 600;">{{ getFormattedHorizontalTotal(range, 'total_value') }}</h6>
                                        </td>
                                    </tr>
                        
                                    <tr class="month-item" v-show="isRowVisible('พื้นที่ใช้สอย')" :style="getHighlightStyle('พื้นที่ใช้สอย')">
                                        <td><h6 class="text-p" style="font-size: 12px; font-weight: 400; padding-left: 15px;">พื้นที่ใช้สอย</h6></td>
                                        <td v-for="month in displayedMonths" :key="month.value + '-area'" style="text-align: right;">
                                            <h6 class="text-p" style="font-size: 13px; font-weight: 400;">{{ getDetailedValue('usable_area', month.value, range) }}</h6>
                                        </td>
                                        <td style="background-color: #FFF3E0; text-align: right;" :style="getHighlightStyle('พื้นที่ใช้สอย')">
                                            <h6 class="text-p" style="font-size: 13px; font-weight: 600;">{{ getFormattedHorizontalTotal(range, 'usable_area') }}</h6>
                                        </td>
                                    </tr>
                        
                                    <tr class="month-item" v-show="isRowVisible('ราคาเฉลี่ย/ตร.ม.')" :style="getHighlightStyle('ราคาเฉลี่ย/ตร.ม.')">
                                        <td><h6 class="text-p" style="font-size: 12px; font-weight: 400; padding-left: 15px;">ราคาเฉลี่ย/ตร.ม.</h6></td>
                                        <td v-for="month in displayedMonths" :key="month.value + '-avg'" style="text-align: right;">
                                            <h6 class="text-p" style="font-size: 13px; font-weight: 400;">{{ getDetailedValue('price_per_sqm', month.value, range) }}</h6>
                                        </td>
                                        <td style="background-color: #FFF3E0; text-align: right;" :style="getHighlightStyle('ราคาเฉลี่ย/ตร.ม.')">
                                            <h6 class="text-p" style="font-size: 13px; font-weight: 600;">{{ getFormattedHorizontalTotal(range, 'price_per_sqm') }}</h6>
                                        </td>
                                    </tr>
                                </template>
                        
                                <tr class="month-item" style="background-color: #fcf8ff;" v-show="isRowVisible('จำนวนหลัง')" :style="getHighlightStyle('จำนวนหลัง')">
                                    <td><h6 class="text-p" style="font-size: 13px; font-weight: 600; color: #F8285A;">จำนวนหลัง (รวม)</h6></td>
                                    <td v-for="month in displayedMonths" :key="month.value + '-total-unit'" style="text-align: right;">
                                        <h6 class="text-p" style="font-size: 14px; font-weight: 600; color: #F8285A;">{{ getFormattedMonthTotal(month.value, 'unit') }}</h6>
                                    </td>
                                    <td style="background-color: #FFF3E0; text-align: right;" :style="getHighlightStyle('จำนวนหลัง')">
                                        <h6 class="text-p" style="font-size: 14px; font-weight: 800; color: #F8285A;">{{ getFormattedGrandTotal('unit') }}</h6>
                                    </td>
                                </tr>
                        
                                <tr class="month-item" style="background-color: #fcf8ff;" v-show="isRowVisible('มูลค่ารวม')" :style="getHighlightStyle('มูลค่ารวม')">
                                    <td><h6 class="text-p" style="font-size: 13px; font-weight: 600; color: #F8285A;">มูลค่ารวม (รวม)</h6></td>
                                    <td v-for="month in displayedMonths" :key="month.value + '-total-value'" style="text-align: right;">
                                        <h6 class="text-p" style="font-size: 14px; font-weight: 600; color: #F8285A;">{{ getFormattedMonthTotal(month.value, 'total_value') }}</h6>
                                    </td>
                                    <td style="background-color: #FFF3E0; text-align: right;" :style="getHighlightStyle('มูลค่ารวม')">
                                        <h6 class="text-p" style="font-size: 14px; font-weight: 800; color: #F8285A;">{{ getFormattedGrandTotal('total_value') }}</h6>
                                    </td>
                                </tr>
                        
                                <tr class="month-item" style="background-color: #fcf8ff;" v-show="isRowVisible('พื้นที่ใช้สอย')" :style="getHighlightStyle('พื้นที่ใช้สอย')">
                                    <td><h6 class="text-p" style="font-size: 13px; font-weight: 600; color: #F8285A;">พื้นที่ใช้สอย (รวม)</h6></td>
                                    <td v-for="month in displayedMonths" :key="month.value + '-total-area'" style="text-align: right;">
                                        <h6 class="text-p" style="font-size: 14px; font-weight: 600; color: #F8285A;">{{ getFormattedMonthTotal(month.value, 'usable_area') }}</h6>
                                    </td>
                                    <td style="background-color: #FFF3E0; text-align: right;" :style="getHighlightStyle('พื้นที่ใช้สอย')">
                                        <h6 class="text-p" style="font-size: 14px; font-weight: 800; color: #F8285A;">{{ getFormattedGrandTotal('usable_area') }}</h6>
                                    </td>
                                </tr>
                                
                                <tr class="month-item" style="background-color: #fcf8ff;" v-show="isRowVisible('ราคาเฉลี่ย/ตร.ม.')" :style="getHighlightStyle('ราคาเฉลี่ย/ตร.ม.')">
                                    <td><h6 class="text-p" style="font-size: 13px; font-weight: 600; color: #F8285A;">ราคาเฉลี่ย/ตร.ม. (รวม)</h6></td>
                                    <td v-for="month in displayedMonths" :key="month.value + '-total-avg'" style="text-align: right;">
                                        <h6 class="text-p" style="font-size: 14px; font-weight: 600; color: #F8285A;">{{ getFormattedMonthTotal(month.value, 'price_per_sqm') }}</h6>
                                    </td>
                                    <td style="background-color: #FFF3E0; text-align: right;" :style="getHighlightStyle('ราคาเฉลี่ย/ตร.ม.')">
                                        <h6 class="text-p" style="font-size: 14px; font-weight: 800; color: #F8285A;">{{ getFormattedGrandTotal('price_per_sqm') }}</h6>
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
.month-item td, .month-item th {
  padding: 8px !important;
  border-bottom: 1px solid #eee;
}

/* ✅ [เพิ่มใหม่] CSS สำหรับการ์ดที่คลิกได้ */
.v-card[style*="cursor: pointer"] {
    transition: transform 0.2s ease-in-out, background-color 0.2s ease-in-out;
}

/* 1. เมื่อ "Hover" หรือ "ถูกคลิก" (Active) -> เปลี่ยน "พื้นหลัง" */
.v-card[style*="cursor: pointer"]:hover,
.v-card.card-is-active {
    background-color: #E3F2FD !important; /* สีฟ้าอ่อน */
    transform: translateY(-2px);
}

/* 2. เมื่อ "Hover" หรือ "Active" -> เปลี่ยน "สีข้อความ" */
.v-card[style*="cursor: pointer"]:hover .text-h4,
.v-card[style*="cursor: pointer"]:hover .textSecondary,
.v-card.card-is-active .text-h4,
.v-card.card-is-active .textSecondary {
    color: #1E88E5 !important; /* สีฟ้าเข้ม */
}
</style>