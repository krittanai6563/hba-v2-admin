<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import * as XLSX from 'xlsx';
import ExcelJS from 'exceljs';
import jsPDF from 'jspdf'; 
import html2canvas from 'html2canvas'; 
import { TomlIcon, DownloadIcon } from 'vue-tabler-icons';

const currentYear = new Date().getFullYear() + 543;
const currentMonth = new Date().getMonth() + 1;
const selectedYear = ref(currentYear.toString());
const yearOptions = Array.from({ length: 2 }, (_, i) => (currentYear - i).toString());

// (!!!) ตัวแปรสำหรับ "จำ" แถวที่กำลังไฮไลต์
const selectedHighlight = ref<(typeof dataTypes)[number] | null>(null);

const regions = ['ทั้งหมด', 'กรุงเทพปริมณฑล', 'ภาคเหนือ', 'ภาคตะวันออกเฉียงเหนือ', 'ภาคกลาง', 'ภาคตะวันออก', 'ภาคใต้', 'ภาคตะวันตก'];
const selectedRegion = ref('ทั้งหมด'); 
const exportLoading = ref(false);
const summaryData = ref<Record<string, Record<string, Record<string, number>>>>({});
// (!!!) NEW: Add state for previous year's data
const previousYearSummaryData = ref<Record<string, Record<string, Record<string, number>>>>({});

const showErrorAlert = ref(false);
const alertMessage = ref('');

const userId = localStorage.getItem('user_id');
const loading = ref(false); // (!!!) เพิ่มตัวแปร loading
const userRole = localStorage.getItem('user_role') || 'user';

const showSnackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('info'); // สีเริ่มต้น
const snackbarTimeout = ref(5000); // 5000ms = 5 วินาที


const priceRanges = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป'];
const dataTypes = ['จำนวนหลัง', 'มูลค่ารวม', 'พื้นที่ใช้สอย', 'ราคาเฉลี่ย/ตร.ม.'] as const;

const typeMap: Record<(typeof dataTypes)[number], 'unit' | 'value' | 'area' | 'price_per_sqm'> = {
  'จำนวนหลัง': 'unit',
  'มูลค่ารวม': 'value',
  'พื้นที่ใช้สอย': 'area',
  'ราคาเฉลี่ย/ตร.ม.': 'price_per_sqm'
};

type YAxisConfig = ApexAxisChartSeries | {
    seriesName: string;
    opposite?: boolean;
    axisTicks?: { show: boolean; };
    axisBorder?: { show: boolean; color?: string; };
    labels?: { show: boolean; formatter?: (val: any) => string; style?: { colors?: string; }; };
    title?: { text: undefined; style?: { color?: string; } };
    offsetX?: number;
}; 
const getMaxQuarter = () => {
  if (selectedYear.value !== currentYear.toString()) return 4;
  if (currentMonth <= 3) return 1;
  if (currentMonth <= 6) return 2;
  if (currentMonth <= 9) return 3;
  return 4;
};

// (!!!) MODIFIED: fetchSummary now fetches both current and previous year
const fetchSummary = async () => {
  if (!userId) return;
  loading.value = true;
  
  const currentYearStr = selectedYear.value;
  const previousYearStr = (parseInt(currentYearStr) - 1).toString();

  try {
    const fetchCurrent = fetch(`https://uat.hba-sales.org/backend/quarter_summary.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        user_id: userId,
        year: currentYearStr,
        role: userRole,
        region: selectedRegion.value 
      })
    });

    const fetchPrevious = fetch(`https://uat.hba-sales.org/backend/quarter_summary.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        user_id: userId,
        year: previousYearStr,
        role: userRole,
        region: selectedRegion.value 
      })
    });

    const [resCurrent, resPrevious] = await Promise.all([fetchCurrent, fetchPrevious]);

    if (!resCurrent.ok) throw new Error(`HTTP ${resCurrent.status} for current year ${currentYearStr}`);
    if (!resPrevious.ok) throw new Error(`HTTP ${resPrevious.status} for previous year ${previousYearStr}`);

    summaryData.value = await resCurrent.json();
    previousYearSummaryData.value = await resPrevious.json();

  } catch (err) {
    console.error('Error fetching summary data:', err);
    summaryData.value = {};
    previousYearSummaryData.value = {};
  } finally {
    loading.value = false;
  }
};

watch(selectedYear, fetchSummary);
watch(selectedRegion, fetchSummary);
onMounted(fetchSummary);

const visibleQuarters = computed(() => {
  const maxQ = getMaxQuarter();
  return ['Q1', 'Q2', 'Q3', 'Q4'].filter((_, i) => i < maxQ).map(q => `${q} ${selectedYear.value}`);
});

const chartCategories = computed(() => {
    return ['', ...visibleQuarters.value, '']; 
});

const padSeries = (data: (number | null)[]) => [null, ...data, null];


// (!!!) Computed สำหรับคำนวณ % QOQ (เทียบ 'มูลค่ารวม')
const qoqValuePercentChangeData = computed(() => {
    const quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
    const changes: (number | null)[] = [];

    const getTotalValue = (quarter: string): number => {
        if (!summaryData.value[quarter]) return 0;
        return priceRanges.reduce((sum, price) => {
            return sum + (summaryData.value[quarter]?.[price]?.['value'] || 0);
        }, 0);
    };

    let previousQuarterValue = 0;

    for (const quarter of quarters) {
        const currentQuarterValue = getTotalValue(quarter);

        if (quarter === 'Q1') {
            changes.push(null); 
        } else {
            if (previousQuarterValue > 0) {
                const change = ((currentQuarterValue - previousQuarterValue) / previousQuarterValue) * 100;
                changes.push(parseFloat(change.toFixed(1)));
            } else if (currentQuarterValue > 0) {
                changes.push(100); 
            } else {
                changes.push(0); 
            }
        }
        previousQuarterValue = currentQuarterValue;
    }
    
    return changes.slice(0, visibleQuarters.value.length);
});


// (!!!) Helpers สำหรับตาราง (QOQ)
function getSpecificMetric(quarter: string, priceRange: string, metric: 'unit' | 'value' | 'area' | 'price_per_sqm'): number {
    if (!summaryData.value[quarter] || !summaryData.value[quarter][priceRange]) return 0;

    if (metric === 'price_per_sqm') {
        const value = summaryData.value[quarter][priceRange]['value'] || 0;
        const area = summaryData.value[quarter][priceRange]['area'] || 0;
        return area > 0 ? (value / area) : 0;
    }
    return summaryData.value[quarter][priceRange][metric] || 0;
};

function getQOQCellData(priceRange: string, quarterLabel: string, metric: 'unit' | 'value' | 'area' | 'price_per_sqm'): number | null {
    const [quarter] = quarterLabel.split(' '); 
    
    if (quarter === 'Q1') return null; 

    let prevQuarter = '';
    if (quarter === 'Q2') prevQuarter = 'Q1';
    else if (quarter === 'Q3') prevQuarter = 'Q2';
    else if (quarter === 'Q4') prevQuarter = 'Q3';

    if (!prevQuarter) return null;

    const currentValue = getSpecificMetric(quarter, priceRange, metric);
    const previousValue = getSpecificMetric(prevQuarter, priceRange, metric);

    if (previousValue > 0) {
        const change = ((currentValue - previousValue) / previousValue) * 100;
        return parseFloat(change.toFixed(1));
    } else if (currentValue > 0) {
        return 100; 
    } else {
        return 0; 
    }
};

function formatPercentage(value: number | null): string {
    if (value === null) return '-'; 
    if (value === 0) return '0.0%';
    const prefix = value > 0 ? '+' : '';
    return `${prefix}${value.toFixed(1)}%`;
};
// (!!!) NEW: Helper to get percentage color class
function getQOQPercentageColor(value: number | null): string {
    if (value === null) return 'text-grey';
    if (value > 0) return 'text-success'; // Green
    if (value < 0) return 'text-error';   // Red
    return 'text-grey'; // Neutral
};

// (!!!) แบบใหม่ที่แก้ไขแล้ว
const baseChartSeries = computed(() => {
    const avgPricePerSqmData = visibleQuarters.value.map((q) => {
        const [quarter] = q.split(' ');
        
        const totalValue = priceRanges.reduce((sum, price) => {
            return sum + (summaryData.value[quarter]?.[price]?.['value'] || 0);
        }, 0);

        const totalArea = priceRanges.reduce((sum, price) => {
            return sum + (summaryData.value[quarter]?.[price]?.['area'] || 0);
        }, 0);

        return totalArea > 0 ? parseFloat((totalValue / totalArea).toFixed(2)) : 0;
    });

    // 1. สร้าง 4 ซีรีส์หลักก่อน
    const series = [
        {
            name: 'จำนวนหลัง',
            type: 'column',
            data: padSeries(visibleQuarters.value.map((q) => {
                const [quarter] = q.split(' ');
                return parseFloat(
                    priceRanges.reduce((sum, price) => sum + (summaryData.value[quarter]?.[price]?.['unit'] || 0), 0).toFixed(0)
                );
            })),
            color: '#F9C80E',
        },
        {
            name: 'มูลค่ารวม',
            type: 'column', 
            data: padSeries(visibleQuarters.value.map((q) => {
                const [quarter] = q.split(' ');
                return parseFloat(
                    priceRanges.reduce((sum, price) => sum + (summaryData.value[quarter]?.[price]?.['value'] || 0), 0).toFixed(2)
                );
            })),
            color: '#2983FF', 
        },
        {
            name: 'พื้นที่ใช้สอย',
            type: 'column',
            data: padSeries(visibleQuarters.value.map((q) => {
                const [quarter] = q.split(' ');
                return parseFloat(
                    priceRanges.reduce((sum, price) => sum + (summaryData.value[quarter]?.[price]?.['area'] || 0), 0).toFixed(2)
                );
            })),
            color: '#00D9E9',
        },
        {
            name: 'ราคาเฉลี่ย/ตร.ม.',
            type: 'column', 
            data: padSeries(avgPricePerSqmData.map(val => val === null ? null : parseFloat(val.toFixed(2)))),
            color: '#E900D9', 
        },
    ];

    // 2. (!!!) ตรวจสอบเงื่อนไข และเพิ่มซีรีส์ที่ 5
    if (visibleQuarters.value.length > 1) {
        series.push({
            name: '% QOQ (มูลค่ารวม)',
            type: 'line',
            data: padSeries(qoqValuePercentChangeData.value),
            color: '#D7263D', 
        });
    }

    // 3. คืนค่า
    return series;
});
// (!!!) MODIFICATION: finalChartSeries (กรองข้อมูลตามปุ่มที่คลิก)
const finalChartSeries = computed(() => {
  const selectedName = selectedHighlight.value;

  // ถ้าไม่ได้เลือก (null) ให้แสดงผลปกติ
  if (!selectedName) {
    return baseChartSeries.value;
  }

  // ถ้าเลือก ให้แสดงเฉพาะซีรีส์ที่เลือก + เส้น QoQ
  const selectedSeries = baseChartSeries.value.find(s => s.name === selectedName);
  const qoqSeries = baseChartSeries.value.find(s => s.name === '% QOQ (มูลค่ารวม)');
  
  // (หากกด 'มูลค่ารวม' ไม่ต้องแสดงเส้นซ้ำ)
  if (selectedName === 'มูลค่ารวม') {
       const valueSeries = baseChartSeries.value.find(s => s.name === 'มูลค่ารวม');
       return valueSeries ? [valueSeries, qoqSeries].filter(Boolean) : [];
  }

  return [selectedSeries, qoqSeries].filter(Boolean) as any[];
});


// (!!!) MODIFICATION: chartOptions เปลี่ยนเป็น computed
// (!!!) MODIFICATION: chartOptions เปลี่ยนเป็น computed
const computedChartOptions = computed(() => {
  
  const isFiltered = selectedHighlight.value !== null;
  const selectedName = selectedHighlight.value;

  // (!!!) ADD THIS: ตรวจสอบว่าซีรีส์ QOQ มีอยู่จริงหรือไม่
  const hasQOQSeries = baseChartSeries.value.some(s => s.name === '% QOQ (มูลค่ารวม)');

  // 1. กำหนดค่า Y-Axes ทั้งหมด (ใช้เป็น "แม่แบบ" เท่านั้น)
  const allYAxesTemplates = [
        {
            seriesName: 'จำนวนหลัง',
            axisTicks: { show: true },
            axisBorder: { show: false },
            offsetX: -40, 
            labels: { show: false },
        },
        {
            seriesName: 'มูลค่ารวม',
            opposite: true, 
            axisTicks: { show: true },
            axisBorder: { show: false },
            offsetX: 0, 
            labels: { show: false },
        },
        {
            seriesName: 'พื้นที่ใช้สอย',
            opposite: false, 
            axisTicks: { show: true },
            axisBorder: { show: false },
            offsetX: -80, 
            labels: { show: false },
        },
        {
            seriesName: 'ราคาเฉลี่ย/ตร.ม.',
            opposite: true, 
            axisTicks: { show: false },
            axisBorder: { show: false},
            offsetX: 0, 
            labels: { show: false },
        },
        {
            seriesName: '% QOQ (มูลค่ารวม)',
            opposite: true, 
            axisTicks: { show: false },
            axisBorder: { show: false, color: '#D7263D' }, 
            labels: { show: false },
            title: { text: undefined }
        },
    ];
  
  // 2. กำหนดค่า Stroke ทั้งหมด (ใช้เป็น "แม่แบบ")
  const allStrokesTemplates = [2, 2, 2, 2, 3];
  
  // 3. เตรียมตัวแปรสำหรับเก็บค่าที่จะใช้
  let finalYAxes: YAxisConfig[];
  let finalStrokeWidth: number[];
  let finalColumnWidth = isFiltered ? '60%' : '50%';

  // 4. กรองแกน Y และ Stroke ถ้ามีการเลือก
  if (isFiltered && selectedName) {
    const selectedYAxis = allYAxesTemplates.find(a => a.seriesName === selectedName);
    // (!!!) หา qoqYAxis เฉพาะเมื่อ hasQOQSeries เป็น true
    const qoqYAxis = hasQOQSeries ? allYAxesTemplates.find(a => a.seriesName === '% QOQ (มูลค่ารวม)') : undefined;
    
    finalYAxes = [selectedYAxis, qoqYAxis].filter(Boolean) as YAxisConfig[];

    const selectedIndex = dataTypes.indexOf(selectedName);

    // (!!!) แก้ไขการคำนวณ stroke ให้ปลอดภัย
    const selectedStroke = selectedIndex > -1 ? allStrokesTemplates[selectedIndex] : 2;
    if (selectedName === 'มูลค่ารวม') {
        // กรณีคลิก 'มูลค่ารวม' จะมีแค่ [value, qoq]
        finalStrokeWidth = hasQOQSeries ? [allStrokesTemplates[1], 3] : [allStrokesTemplates[1]];
    } else {
        finalStrokeWidth = hasQOQSeries ? [selectedStroke, 3] : [selectedStroke];
    }
  
  } else {
    // (!!!) KEY CHANGE: ถ้าไม่ได้กรอง ให้ใช้ "แม่แบบ" ตามจำนวนซีรีส์ที่มีจริง
    finalYAxes = hasQOQSeries ? allYAxesTemplates : allYAxesTemplates.slice(0, 4);
    finalStrokeWidth = hasQOQSeries ? allStrokesTemplates : allStrokesTemplates.slice(0, 4);
  }

  // 5. สร้าง Object Options สุดท้าย
  return {
    chart: {
        height: 350,
        type: 'line', 
        stacked: false,
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
        toolbar: { show: true, tools: { download: true } },
        responsive: [{ breakpoint: 1000, options: { chart: { width: '100%' } } }],
        offsetX: 0, 
        padding: { left: 0, right: 0, top: 0, bottom: 0 },
    },
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: finalColumnWidth, // (!!!) Dynamic
            dataLabels: { position: 'top', offsetY: 0 },
        },
        line: {
            dataLabels: { position: 'top', offsetY: 0 },
            curve: 'smooth',
        },
    },
    dataLabels: {
        enabled: true,  
        position: 'top',
        offsetY: -13,
        style: { fontSize: '10px' },
        formatter: (value: any, { seriesIndex }: { seriesIndex: number }) => {  
            if (value === null) return '';
            
            // (!!!) KEY CHANGE: ตรวจสอบจากชื่อซีรีส์ ไม่ใช่ index
            // เราจะใช้ finalChartSeries.value ซึ่งเป็น computed ที่ถูกต้อง (มี 4 หรือ 5 ซีรีส์)
            const seriesName = finalChartSeries.value[seriesIndex]?.name;
            if (seriesName === '% QOQ (มูลค่ารวม)') {
                return ''; // ไม่ต้องแสดง % บนเส้นกราฟ
            }
            
            if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
            if (value >= 1000) return (value / 1000).toFixed(1) + 'K';
            return value.toLocaleString('th-TH', { maximumFractionDigits: 1 });
        }
    },
    stroke: {
        width: finalStrokeWidth, // (!!!) Dynamic
        curve: 'smooth',
    },
    grid: {
        show: true,
        strokeDashArray: 4, 
        borderColor: 'rgba(0, 0, 0, 0.1)',  
    },
    markers: {
        size: 5, 
        hover: { size: 7 }
    },
    xaxis: {
        categories: chartCategories.value,  // (!!!) Dynamic (จาก computed)
        labels: {
            rotate: -45, 
            style: { fontSize: '12px', colors: '#6c757d' },
            formatter: (val: any) => (val && typeof val === 'string' && val.startsWith('Q')) ? val : '',
        },
        axisBorder: { show: true, color: '#6c757d', height: 1, width: '100%', offsetX: 0, offsetY: 0 },
        axisTicks: { show: true, borderType: 'solid', color: '#6c757d', height: 6, offsetX: 0, offsetY: 0 }
    },
    yaxis: finalYAxes, // (!!!) Dynamic
    tooltip: {
        fixed: { enabled: false },
        y: {
            formatter: (val: number, { seriesIndex }: { seriesIndex: number }) => {
                if (val === undefined || val === null) return 'N/A';
                
                // (!!!) Dynamic: ตรวจสอบชื่อซีรีส์ (โค้ดส่วนนี้ของคุณดีอยู่แล้ว)
                const seriesName = finalChartSeries.value[seriesIndex]?.name;
                if (seriesName === '% QOQ (มูลค่ารวม)') {
                    return val.toFixed(1) + ' %';
                }
                
                return val.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }
    },
    legend: {
        horizontalAlign: 'center',
        offsetX: 0,
    },
  };
});


const formatNumber = (value: number, isDecimal = false) => {
  if (value === null || value === undefined) {
      return isDecimal ? '0.00' : '0';
  }
  return isDecimal
    ? value.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : value.toLocaleString('th-TH', { maximumFractionDigits: 0 }); // Ensure integer for 'unit'
};


const getQuarterTotal = (quarterLabel: string, type: 'unit' | 'value' | 'area') => {
  const [q] = quarterLabel.split(' ');
  const total = priceRanges.reduce((sum, price) => {
    return sum + (summaryData.value[q]?.[price]?.[type] || 0);
  }, 0);
  return formatNumber(total, type === 'value' || type === 'area');
};

const getRowTotal = (label: string, type: 'unit' | 'value' | 'area' | 'price_per_sqm') => {

  // Calculate total avg price for the year for this price range
  if (type === 'price_per_sqm') {
      const totalValue = visibleQuarters.value.reduce((sum, q) => {
          const [quarter] = q.split(' ');
          return sum + (summaryData.value[quarter]?.[label]?.['value'] || 0);
      }, 0);
      const totalArea = visibleQuarters.value.reduce((sum, q) => {
          const [quarter] = q.split(' ');
          return sum + (summaryData.value[quarter]?.[label]?.['area'] || 0);
      }, 0);
      
      const avgTotal = totalArea > 0 ? (totalValue / totalArea) : 0;
      return formatNumber(avgTotal, true); // Format as decimal
  }

  // Calculate sum for 'unit', 'value', 'area'
  const total = visibleQuarters.value.reduce((sum, q) => {
    const [quarter] = q.split(' ');
    return sum + (summaryData.value[quarter]?.[label]?.[type] || 0); 
  }, 0);
    
  // Format 'value' and 'area' as decimal, 'unit' as integer
  return formatNumber(total, type === 'value' || type === 'area');
};

const getTotalYearTotal = (type: 'unit' | 'value' | 'area') => {
  const rawTotal = visibleQuarters.value.reduce((sumQ, q) => {
      const [quarter] = q.split(' ');
      return (
        sumQ +
        priceRanges.reduce((sumP, price) => {
          return sumP + (summaryData.value[quarter]?.[price]?.[type] || 0);
        }, 0)
      );
    }, 0);
    
  return formatNumber(rawTotal, type === 'value' || type === 'area');
};

// (!!!) ADDED HELPER FUNCTIONS FOR TOTAL AVG PRICE
const getQuarterAvgPrice = (quarterLabel: string) => {
  const [q] = quarterLabel.split(' ');
  const totalValue = priceRanges.reduce((sum, price) => {
    return sum + (summaryData.value[q]?.[price]?.['value'] || 0);
  }, 0);
  const totalArea = priceRanges.reduce((sum, price) => {
    return sum + (summaryData.value[q]?.[price]?.['area'] || 0);
  }, 0);
  
  const avg = totalArea > 0 ? (totalValue / totalArea) : 0;
  return formatNumber(avg, true); // Use true for decimal formatting
};

const getTotalYearAvgPrice = () => {
    const totalValue = getRawTotalYearTotal('value');
    const totalArea = getRawTotalYearTotal('area');
    const avg = totalArea > 0 ? (totalValue / totalArea) : 0;
    return formatNumber(avg, true); // Use true for decimal formatting
};


// (!!!) NEW: Helpers for Total Rows QOQ
function getRawQuarterTotal(quarter: string, metric: 'unit' | 'value' | 'area'): number {
    if (!summaryData.value[quarter]) return 0;
    return priceRanges.reduce((sum, price) => {
        return sum + (summaryData.value[quarter]?.[price]?.[metric] || 0);
    }, 0);
}

function getRawQuarterAvgPrice(quarter: string): number {
    if (!summaryData.value[quarter]) return 0;
    const totalValue = getRawQuarterTotal(quarter, 'value');
    const totalArea = getRawQuarterTotal(quarter, 'area');
    return totalArea > 0 ? (totalValue / totalArea) : 0;
}

function getQuarterTotalQOQ(quarterLabel: string, metric: 'unit' | 'value' | 'area'): number | null {
    const [quarter] = quarterLabel.split(' '); 
    
    if (quarter === 'Q1') return null; 

    let prevQuarter = '';
    if (quarter === 'Q2') prevQuarter = 'Q1';
    else if (quarter === 'Q3') prevQuarter = 'Q2';
    else if (quarter === 'Q4') prevQuarter = 'Q3';

    if (!prevQuarter) return null;

    const currentValue = getRawQuarterTotal(quarter, metric);
    const previousValue = getRawQuarterTotal(prevQuarter, metric);

    return calculatePercentageChange(currentValue, previousValue);
}

function getQuarterAvgPriceQOQ(quarterLabel: string): number | null {
    const [quarter] = quarterLabel.split(' '); 
    
    if (quarter === 'Q1') return null; 

    let prevQuarter = '';
    if (quarter === 'Q2') prevQuarter = 'Q1';
    else if (quarter === 'Q3') prevQuarter = 'Q2';
    else if (quarter === 'Q4') prevQuarter = 'Q3';

    if (!prevQuarter) return null;

    const currentValue = getRawQuarterAvgPrice(quarter);
    const previousValue = getRawQuarterAvgPrice(prevQuarter);

    return calculatePercentageChange(currentValue, previousValue);
}
// (!!!) จบ Helpers QOQ แถว Total


// (!!!) NEW: Helpers for YoY Calculation
// Helper to calculate percentage change
function calculatePercentageChange(currentValue: number, previousValue: number): number | null {
  if (previousValue > 0) {
    const change = ((currentValue - previousValue) / previousValue) * 100;
    return parseFloat(change.toFixed(1));
  } else if (currentValue > 0) {
    return 100; 
  } else {
    return 0; 
  }
}

// Helper to get raw totals for specific quarters from a data source
function getRawTotalForQuarters(
  data: Record<string, Record<string, Record<string, number>>>, 
  priceRange: string, 
  metric: 'unit' | 'value' | 'area' | 'price_per_sqm', 
  quarters: string[]
): number {
  if (metric === 'price_per_sqm') {
    const totalValue = quarters.reduce((sum, q) => sum + (data[q]?.[priceRange]?.['value'] || 0), 0);
    const totalArea = quarters.reduce((sum, q) => sum + (data[q]?.[priceRange]?.['area'] || 0), 0);
    return totalArea > 0 ? (totalValue / totalArea) : 0;
  } else {
    return quarters.reduce((sum, q) => sum + (data[q]?.[priceRange]?.[metric] || 0), 0);
  }
}

// Function for the template: Calculates YoY for a specific row
function getRowTotalYoY(priceRange: string, metric: 'unit' | 'value' | 'area' | 'price_per_sqm'): number | null {
  const quartersToCompare = visibleQuarters.value.map(q => q.split(' ')[0]);
  
  const currentValue = getRawTotalForQuarters(summaryData.value, priceRange, metric, quartersToCompare);
  const previousValue = getRawTotalForQuarters(previousYearSummaryData.value, priceRange, metric, quartersToCompare);
  
  return calculatePercentageChange(currentValue, previousValue);
}

// Helper to get raw GRAND totals for specific quarters
function getRawGrandTotalForQuarters(
  data: Record<string, Record<string, Record<string, number>>>, 
  metric: 'unit' | 'value' | 'area' | 'price_per_sqm', 
  quarters: string[]
): number {
  
  let totalValue = 0;
  let totalArea = 0;
  let totalUnit = 0;

  for (const q of quarters) {
    if (!data[q]) continue;
    for (const priceRange of priceRanges) {
      totalValue += data[q]?.[priceRange]?.['value'] || 0;
      totalArea += data[q]?.[priceRange]?.['area'] || 0;
      totalUnit += data[q]?.[priceRange]?.['unit'] || 0;
    }
  }

  if (metric === 'price_per_sqm') {
    return totalArea > 0 ? (totalValue / totalArea) : 0;
  } else if (metric === 'value') {
    return totalValue;
  } else if (metric === 'area') {
    return totalArea;
  } else { // 'unit'
    return totalUnit;
  }
}

// Function for the template: Calculates YoY for the grand total rows
function getGrandTotalYoY(metric: 'unit' | 'value' | 'area' | 'price_per_sqm'): number | null {
  const quartersToCompare = visibleQuarters.value.map(q => q.split(' ')[0]);

  const currentValue = getRawGrandTotalForQuarters(summaryData.value, metric, quartersToCompare);
  const previousValue = getRawGrandTotalForQuarters(previousYearSummaryData.value, metric, quartersToCompare);

  return calculatePercentageChange(currentValue, previousValue);
}
// (!!!) จบ Helpers YoY


// (!!!) Helpersสำหรับสถิติหัวกราฟ (QOQ)
function formatStatNumber(val: number): string {
     return val.toLocaleString('th-TH', { maximumFractionDigits: 2 });
};

function getPercentageColor(value: number | null): string {
    if (value === null) return 'text-grey';
    if (value > 0) return 'text-success';
    if (value < 0) return 'text-error';
    return 'text-grey';
};

function formatPercentageHelper(val: number): string {
    if (val === 0 || !val) return '0.0%';
    const prefix = val > 0 ? '+' : '';
    return `${prefix}${val.toFixed(1)}%`;
};

const latestVisibleQuarterKey = computed(() => {
    const quarters = visibleQuarters.value; 
    if (quarters.length === 0) return null;
    return quarters[quarters.length - 1].split(' ')[0]; 
});

const previousVisibleQuarterKey = computed(() => {
    if (latestVisibleQuarterKey.value === 'Q2') return 'Q1';
    if (latestVisibleQuarterKey.value === 'Q3') return 'Q2';
    if (latestVisibleQuarterKey.value === 'Q4') return 'Q3';
    return null; 
});

const latestQuarterQOQDifference = computed(() => {
    const qKey = latestVisibleQuarterKey.value;
    const prevQKey = previousVisibleQuarterKey.value;
    
    if (!qKey || !prevQKey || !summaryData.value[qKey] || !summaryData.value[prevQKey]) return 0; 

    const latestValue = priceRanges.reduce((sum, price) => {
        return sum + (summaryData.value[qKey]?.[price]?.['value'] || 0);
    }, 0);

    const previousValue = priceRanges.reduce((sum, price) => {
        return sum + (summaryData.value[prevQKey]?.[price]?.['value'] || 0);
    }, 0);

    return latestValue - previousValue;
});

const latestQuarterQOQPercent = computed(() => {
    const qoqData = qoqValuePercentChangeData.value;
    
    if (qoqData.length < 2) return 0; 

    const latestChange = qoqData[qoqData.length - 1];
    
    return latestChange === null ? 0 : latestChange;
});
// (!!!) จบ Helpers สถิติหัวกราฟ


// (!!!) Helpers สำหรับ Card 4 ใบ (getValue)
function getRawTotalYearTotal(type: 'unit' | 'value' | 'area'): number {
    return visibleQuarters.value.reduce((sumQ, q) => {
      const [quarter] = q.split(' ');
      return (
        sumQ +
        priceRanges.reduce((sumP, price) => {
          return sumP + (summaryData.value[quarter]?.[price]?.[type] || 0);
        }, 0)
      );
    }, 0);
}

function getValue(type: (typeof dataTypes)[number]): string {
    const metric = typeMap[type]; 

    if (metric === 'price_per_sqm') {
        const totalValue = getRawTotalYearTotal('value');
        const totalArea = getRawTotalYearTotal('area');
        const avg = totalArea > 0 ? (totalValue / totalArea) : 0;
        
        return avg.toLocaleString('th-TH', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        });
    }
    
    const rawTotal = getRawTotalYearTotal(metric as 'unit' | 'value' | 'area');
    
    return formatNumber(rawTotal, metric === 'value' || metric === 'area'); 
}
// (!!!) จบ Helpers Card 4 ใบ


// (!!!) Helpers สำหรับไฮไลต์ตาราง
function highlightRow(metric: (typeof dataTypes)[number]) {
  if (selectedHighlight.value === metric) {
    selectedHighlight.value = null;
  } else {
    selectedHighlight.value = metric;
  }
}


// (!!!) NEW: Computed สำหรับแสดงสถิติ QOQ แบบ Dynamic ตามปุ่มที่คลิก
const dynamicStatData = computed(() => {
    const qKey = latestVisibleQuarterKey.value;
    const prevQKey = previousVisibleQuarterKey.value;
    
    // ถ้าเป็น Q1 หรือไม่มีไตรมาสก่อนหน้า ให้ trả về ค่าว่าง
    if (!qKey || !prevQKey) {
        return { label: 'มูลค่ารวม', percent: null };
    }

    // 1. หา Metric ที่เลือก (ถ้าไม่เลือก ให้ใช้ 'มูลค่ารวม' เป็นค่าเริ่มต้น)
    const selectedMetricName = selectedHighlight.value || 'มูลค่ารวม';
    const metricType = typeMap[selectedMetricName];

    let currentValue = 0;
    let previousValue = 0;

    // 2. ดึงค่า Current vs Previous ตาม Metric ที่เลือก
    // (เราใช้ฟังก์ชันที่มีอยู่แล้วในไฟล์ของคุณ)
    if (metricType === 'price_per_sqm') {
        currentValue = getRawQuarterAvgPrice(qKey);
        previousValue = getRawQuarterAvgPrice(prevQKey);
    } else if (metricType === 'unit' || metricType === 'value' || metricType === 'area') {
        currentValue = getRawQuarterTotal(qKey, metricType);
        previousValue = getRawQuarterTotal(prevQKey, metricType);
    }

    // 3. คำนวณ % change
    // (ใช้ฟังก์ชันที่มีอยู่แล้วในไฟล์ของคุณ)
    const percentChange = calculatePercentageChange(currentValue, previousValue);

    return {
        label: selectedMetricName,
        percent: percentChange
    };
});
function getHighlightStyle(type: (typeof dataTypes)[number]) {
  if (selectedHighlight.value !== type) return null;

  if (type === 'จำนวนหลัง') return { backgroundColor: '#E3F2FD' }; 
  if (type === 'มูลค่ารวม') return { backgroundColor: '#EDE7F6' }; 
  if (type === 'พื้นที่ใช้สอย') return { backgroundColor: '#FFEBEE' }; 
  if (type === 'ราคาเฉลี่ย/ตร.ม.') return { backgroundColor: '#FFF8E1' }; 

  return null;
}
// (!!!) จบ Helpers ไฮไลต์ตาราง
// (!!!) เพิ่มฟังก์ชันนี้: สำหรับตรวจสอบการแสดงผลแถว
function isRowVisible(type: (typeof dataTypes)[number]): boolean {
  // ถ้าไม่มีปุ่มไหนถูกเลือก (selectedHighlight เป็น null) ให้แสดงทุกแถว
  if (selectedHighlight.value === null) {
    return true;
  }
  // ถ้ามีปุ่มถูกเลือก ให้แสดงเฉพาะแถวที่ตรงกับปุ่มนั้น
  return selectedHighlight.value === type;
}


// (!!!) NEW: Helper for ExcelJS colors (ควรมีฟังก์ชันนี้อยู่ด้วย)
function getExcelColor(value: number | null): string {
    if (value === null) return 'FF808080'; // สีเทา
    if (value > 0) return 'FF008000';   // สีเขียว
    if (value < 0) return 'FFFF0000';   // สีแดง
    return 'FF808080'; // สีเทา
}



const exportToExcel = async () => {
    exportLoading.value = true;
    showSnackbar.value = false;
    
    try {
        const wb = new ExcelJS.Workbook();
        const ws = wb.addWorksheet('ตารางรายงาน', {
            views: [{ state: 'frozen', ySplit: 2 }] // ตรึงแถว Header
        });

        // (!!!) NEW: Define font name
        const fontName = 'TH Sarabun PSK';

        const headerCols = visibleQuarters.value.map(q => q);
        const ytdLabel = `${selectedYear.value} (YTD)`;

        // --- Row 1 & 2: Main Headers ---
        const titleRow = ws.addRow([
            `ตารางแบ่งตามไตรมาส ประจำปี ${selectedYear.value} (พื้นที่: ${selectedRegion.value})`
        ]);
        // (!!!) MODIFIED: Add font
        titleRow.font = { name: fontName, bold: true, size: 16 };
        ws.mergeCells(1, 1, 1, headerCols.length + 2);

        const headerRow = ws.addRow([
            'Financial Summary',
            ...headerCols,
            ytdLabel
        ]);
        // (!!!) MODIFIED: Add font
        headerRow.font = { name: fontName, bold: true, size: 12 };
        headerRow.alignment = { horizontal: 'center', vertical: 'middle' };
        headerRow.getCell(1).alignment = { horizontal: 'left' };
        headerRow.getCell(headerCols.length + 2).alignment = { horizontal: 'right' };
        headerRow.eachCell(cell => {
            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
            cell.border = { bottom: { style: 'medium', color: { argb: 'FF00A6D4' } } };
        });

        ws.addRow([]); // Empty spacer row
        let currentRowNum = 4; // เริ่มต้นข้อมูลที่แถว 4

        const ytdBgFill = { type: 'pattern' as const, pattern: 'solid' as const, fgColor: { argb: 'FFFFF3E0' } };
        const priceRangeFill = { type: 'pattern' as const, pattern: 'solid' as const, fgColor: { argb: 'FFFCF8FF' } };
        // (!!!) MODIFIED: Add font
        const priceRangeFont = { name: fontName, bold: true, size: 12, color: { argb: 'FF725AF2' } };

        // --- Data Rows (Price Ranges) ---
        for (const priceRange of priceRanges) {
            // แถวหัวข้อ Price Range
            const priceRow = ws.addRow([priceRange]);
            ws.mergeCells(currentRowNum, 1, currentRowNum, headerCols.length + 2);
            priceRow.font = priceRangeFont;
            priceRow.fill = priceRangeFill;
            currentRowNum++;

            // แถวข้อมูล
            for (const type of dataTypes) {
                const metricType = typeMap[type];
                const isUnit = type === 'จำนวนหลัง';
                const numFmt = isUnit ? '#,##0' : '#,##0.00';
                const rowData: any[] = [type];

                // Quarters Data
                for (const q of visibleQuarters.value) {
                    const quarter = q.split(' ')[0];
                    const value = getSpecificMetric(quarter, priceRange, metricType);
                    
                    if (quarter === 'Q1') {
                        rowData.push(value); // ใส่ค่าดิบ
                    } else {
                        const qoqPercent = getQOQCellData(priceRange, q, metricType);
                        // (!!!) MODIFIED: Add font
                        rowData.push({
                            richText: [
                                { text: formatNumber(value, !isUnit) + '\n', font: { name: fontName, size: 11 } },
                                { text: `(${formatPercentage(qoqPercent)})`, font: { name: fontName, color: { argb: getExcelColor(qoqPercent) }, size: 9 } }
                            ]
                        });
                    }
                }

                // YTD Total Data
                const rawYTDValue = getRawTotalForQuarters(summaryData.value, priceRange, metricType, visibleQuarters.value.map(q => q.split(' ')[0]));
                const yoyPercent = getRowTotalYoY(priceRange, metricType);
                // (!!!) MODIFIED: Add font
                rowData.push({
                    richText: [
                        { text: formatNumber(rawYTDValue, !isUnit) + '\n', font: { name: fontName, size: 11 } },
                        { text: `(${formatPercentage(yoyPercent)})`, font: { name: fontName, color: { argb: getExcelColor(yoyPercent) }, size: 9 } }
                    ]
                });
                
                const dataRow = ws.addRow(rowData);
                dataRow.alignment = { vertical: 'middle', horizontal: 'right', wrapText: true };
                // (!!!) MODIFIED: Add font to cell 1
                dataRow.getCell(1).alignment = { horizontal: 'left' };
                dataRow.getCell(1).font = { name: fontName, size: 11 };
                
                // (!!!) MODIFIED: Add font to Q1 cell
                if (visibleQuarters.value.length > 0 && visibleQuarters.value[0].startsWith('Q1')) {
                    dataRow.getCell(2).numFmt = numFmt;
                    dataRow.getCell(2).value = getSpecificMetric('Q1', priceRange, metricType);
                    dataRow.getCell(2).font = { name: fontName, size: 11 };
                }
                dataRow.getCell(headerCols.length + 2).fill = ytdBgFill;
                currentRowNum++;
            }
        }

        // --- Total Rows ---
        // (!!!) MODIFIED: Add font
        const totalRowFont = { name: fontName, bold: true, color: { argb: 'FFF8285A' }, size: 11 };
        const totalRowFill = { type: 'pattern' as const, pattern: 'solid' as const, fgColor: { argb: 'FFFCF8FF' } };
        const totalYtdFill = { type: 'pattern' as const, pattern: 'solid' as const, fgColor: { argb: 'FFFFF3E0' } };

        // Helper to add total rows
        const addTotalRow = (label: string, metric: 'unit' | 'value' | 'area' | 'price_per_sqm') => {
            const isUnit = metric === 'unit';
            const numFmt = isUnit ? '#,##0' : '#,##0.00';
            const rowData: any[] = [{ richText: [{ text: label, font: totalRowFont }] }];

            for (const q of visibleQuarters.value) {
                const quarter = q.split(' ')[0];
                const value = (metric === 'price_per_sqm') ? getRawQuarterAvgPrice(quarter) : getRawQuarterTotal(quarter, metric);
                
                if (quarter === 'Q1') {
                    rowData.push(value); // ใส่ค่าดิบ
                } else {
                    const qoqPercent = (metric === 'price_per_sqm') ? getQuarterAvgPriceQOQ(q) : getQuarterTotalQOQ(q, metric);
                    // (!!!) MODIFIED: Add font
                    rowData.push({
                        richText: [
                            { text: formatNumber(value, !isUnit) + '\n', font: totalRowFont },
                            { text: `(${formatPercentage(qoqPercent)})`, font: { name: fontName, color: { argb: getExcelColor(qoqPercent) }, size: 9 } }
                        ]
                    });
                }
            }
            
            // YTD Total
            const rawYTDValue = getRawGrandTotalForQuarters(summaryData.value, metric, visibleQuarters.value.map(q => q.split(' ')[0]));
            const yoyPercent = getGrandTotalYoY(metric);
            // (!!!) MODIFIED: Add font
            rowData.push({
                richText: [
                    { text: formatNumber(rawYTDValue, !isUnit) + '\n', font: { ...totalRowFont, bold: true } }, // เน้นหนา YTD
                    { text: `(${formatPercentage(yoyPercent)})`, font: { name: fontName, color: { argb: getExcelColor(yoyPercent) }, size: 9, bold: true } }
                ]
            });

            const totalRow = ws.addRow(rowData);
            totalRow.eachCell((cell, colNumber) => {
                cell.fill = totalRowFill;
                cell.alignment = { vertical: 'middle', horizontal: 'right', wrapText: true };
                if (colNumber === 1) cell.alignment = { horizontal: 'left' };
                
                // (!!!) MODIFIED: Add font to Q1 cell
                if (colNumber === 2 && visibleQuarters.value[0].startsWith('Q1')) {
                    cell.numFmt = numFmt;
                    cell.font = totalRowFont; // Q1 ไม่มี RichText ต้องสั่งสี/font เอง
                }
                if (colNumber === headerCols.length + 2) {
                    cell.fill = totalYtdFill; // สีพื้นหลัง YTD
                }
            });
        };

        addTotalRow('จำนวนหลัง (รวม)', 'unit');
        addTotalRow('มูลค่ารวม (รวม)', 'value');
        addTotalRow('พื้นที่ใช้สอย (รวม)', 'area');
        addTotalRow('ราคาเฉลี่ย/ตร.ม. (รวม)', 'price_per_sqm');

        // --- Column Widths ---
        ws.columns = [
            { width: 35 }, // Financial Summary
            ...headerCols.map(() => ({ width: 22 })), // Quarters
            { width: 25 }  // YTD
        ];

        // --- Save File ---
        const buffer = await wb.xlsx.writeBuffer();
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `รายงานแบ่งตามไตรมาส_${selectedYear.value}_${selectedRegion.value}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        snackbarText.value = 'สร้างไฟล์ Excel สำเร็จแล้ว';
        snackbarColor.value = 'success';
        showSnackbar.value = true;

    } catch (err: any) {
        console.error('Error exporting to Excel:', err);
        // (!!!) NEW: แสดง Snackbar เมื่อล้มเหลว
        snackbarText.value = `ไม่สามารถสร้าง Excel ได้: ${err.message || 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ'}`;
        snackbarColor.value = 'error';
        showSnackbar.value = true;
    } finally {
        exportLoading.value = false;
        // (!!!) ลบบรรทัด 'exportLoading.value = false;' ที่ซ้ำซ้อนตรงนี้ออกไป
    }
};

const blobToDataURL = (blob: Blob): Promise<string> => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      resolve(reader.result as string); // 2. คืนค่า Data URL ทั้งหมด
    };
    reader.onerror = (error) => reject(error);
    reader.readAsDataURL(blob); // 3. อ่านเป็น DataURL
  });
};

// (!!!) 2. แทนที่ฟังก์ชัน exportToPDF เดิมทั้งหมดด้วยโค้ดนี้
// (!!!) 2. แทนที่ฟังก์ชัน exportToPDF เดิมทั้งหมดด้วยโค้ดนี้
const exportToPDF = async () => {
  exportLoading.value = true;
  showSnackbar.value = false; // ซ่อน Snackbar เก่าก่อน
  
  const tableElement = document.getElementById('table-to-export');
  if (!tableElement) {
    console.error('Table element not found!');
    snackbarText.value = 'ไม่พบองค์ประกอบตาราง (Table element not found!)';
    snackbarColor.value = 'error';
    showSnackbar.value = true;
    exportLoading.value = false;
    return;
  }

  try {
    // === 1. โหลดทรัพยากร (ฟอนต์ และ โลโก้) ===

    // (!!!) 1a. (MODIFIED) โหลดฟอนต์ (ใช้วิธี DataURL แล้วตัดส่วนหัว)
   const fontUrl = '/fonts/THSarabunNew.ttf';
    let fontBase64 = ''; // นี่คือ Base64 ที่ *ไม่* มี prefix
    try {
        const fontResponse = await fetch(fontUrl);
        if (!fontResponse.ok) throw new Error('Failed to fetch local font: /fonts/THSarabunNew.ttf');
        const fontBlob = await fontResponse.blob();
        
        // ใช้วิธี DataURL ที่เสถียร
        const fontDataURL = await blobToDataURL(fontBlob); 
        
        // (!!!) Key: ตัดส่วนหัว "data:application/octet-stream;base64," (หรือ "data:font/ttf;...") ออก
        fontBase64 = fontDataURL.split(',')[1]; 
        if (!fontBase64) throw new Error('Failed to parse Base64 from font Data URL');

    } catch (fontErr: any) {
        throw new Error(`Font loading failed: ${fontErr.message}`);
    }


    // (!!!) 1b. (MODIFIED) โหลดโลโก้ (ใช้วิธี DataURL ที่สมบูรณ์)
    // (ใช้ Path ที่ถูกต้องตามที่คุณระบุ)
    const logoUrl = '/assets/images/image-28-2.png';
    let logoDataURL = ''; // นี่คือ Data URL ที่ *มี* prefix
    try {
        const logoResponse = await fetch(logoUrl);
        if (!logoResponse.ok) throw new Error('Logo file not found in /assets/images/image-28-2.png');
        const logoBlob = await logoResponse.blob();
        
        // ใช้วิธี DataURL (ตัวนี้เราจะใช้ทั้ง URL)
        logoDataURL = await blobToDataURL(logoBlob); 
    } catch (imgErr) {
        console.error(imgErr); // ถ้าหาโลโก้ไม่เจอ ก็จะแค่ข้ามไป ไม่ให้ error
    }

    // === 2. สร้าง PDF และตั้งค่าฟอนต์ ===
    const pdf = new jsPDF('p', 'mm', 'a4'); 
    const fontName = 'THSarabunNew';
    const fontStyle = 'normal';
    
    // (!!!) 2a. (MODIFIED) ส่ง Base64 string ที่ถูกต้อง (ไม่มี prefix)
    // (วิธีนี้จะแก้ปัญหา 'No unicode cmap' และ 'vFS error')
    pdf.addFileToVFS('THSarabunNew.ttf', atob(fontBase64)); 
    pdf.addFont('THSarabunNew.ttf', fontName, fontStyle);
    pdf.setFont(fontName, fontStyle);
    
    // === 3. สร้างหน้าปก (โลโก้ + ข้อความ) ===
    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageCenter = pageWidth / 2;
    let currentY = 90; // (!!!) ปรับตำแหน่งเริ่มต้นตามที่คุยกัน

    // (!!!) 3a. (MODIFIED) เพิ่มโลโก้ (ด้วย Data URL ที่สมบูรณ์)
    if (logoDataURL) {
        const logoWidth = 60; 
        const logoHeight = 70; // (!!!) ปรับความสูงโลโก้ตามที่คุยกัน
        const logoX = pageCenter - (logoWidth / 2); 
        
        // (วิธีนี้จะแก้ปัญหา 'wrong PNG signature' เพราะเราส่ง "data:image/png;base64,...")
        pdf.addImage(logoDataURL, 'PNG', logoX, currentY, logoWidth, logoHeight);
        
        currentY += logoHeight + 15; 
    } else {
        currentY = 100; 
    }

   
    pdf.setFontSize(30);
    pdf.text('รายงานแบ่งตามไตรมาส', pageCenter, currentY, { align: 'center' });
    currentY += 10; 
    pdf.setFontSize(20);
    const combinedText = `ประจำปี: ${selectedYear.value}  |  พื้นที่: ${selectedRegion.value}`;
    pdf.text(combinedText, pageCenter, currentY, { align: 'center' });

    // (!!!) 4. (NEW) เพิ่มวันที่อัพเดต
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
    
    currentY += 10; // ขยับลงมาอีก
    pdf.setFontSize(15); // ตั้งค่าฟอนต์ให้เล็กลง
    pdf.text(dateString, pageCenter, currentY, { align: 'center' });
    // === จบหน้าปก ===

    // 7. ถ่ายรูปตาราง
    const canvas = await html2canvas(tableElement, {
      scale: 2,
      useCORS: true,
      logging: false,
    });
    
    const imgData = canvas.toDataURL('image/png'); // (โค้ดเดิมของคุณตรงนี้ถูกต้อง)

    // 8. เพิ่มหน้าใหม่สำหรับตาราง
    pdf.addPage();

    // 9. คำนวณสัดส่วน (ส่วนนี้ถูกต้องอยู่แล้ว)
    const pageMargin = 10; 
    const pdfWidth = pdf.internal.pageSize.getWidth() - (pageMargin * 2); 
    const pdfHeight = pdf.internal.pageSize.getHeight() - (pageMargin * 2); 

   
    const imgHeight = pdfHeight; // <--- 1. ยึดความสูง 100% เป็นหลัก
    const imgWidth = canvas.width * imgHeight / canvas.height; // <--- 2. คำนวณความกว้างตามสัดส่วน
    
    let heightLeft = imgHeight;
    let position = pageMargin; 

    pdf.addImage(imgData, 'PNG', pageMargin, position, imgWidth, imgHeight); 
    heightLeft -= pdfHeight;

    // 10. วนลูปเพิ่มหน้า (ส่วนนี้ถูกต้องอยู่แล้ว)
    while (heightLeft > 0) {
      position = heightLeft - imgHeight + pageMargin; 
      pdf.addPage();
      // (!!!) 4. ใช้ imgWidth และ imgHeight ที่คำนวณใหม่
      pdf.addImage(imgData, 'PNG', pageMargin, position, imgWidth, imgHeight); 
      heightLeft -= pdfHeight;
    }
    
    // 11. บันทึกไฟล์
    pdf.save(`รายงานแบ่งตามไตรมาส_${selectedYear.value}_${selectedRegion.value}.pdf`);

    // (!!!) NEW: แสดง Snackbar เมื่อสำเร็จ
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

// (!!!) NEW: Computed สำหรับเปลี่ยนหน่วยของตาราง
const tableUnitSubtitle = computed(() => {
  const selected = selectedHighlight.value; // ดึงค่าที่กำลังไฮไลต์อยู่

  if (selected === 'จำนวนหลัง') {
    return '(หน่วย : หลัง)';
  }
  if (selected === 'มูลค่ารวม') {
    return '(หน่วย : ล้านบาท)';
  }
  if (selected === 'พื้นที่ใช้สอย') {
    return '(หน่วย : ตร.ม.)'; // (คุณอาจต้องปรับถ้าหน่วยเป็นอย่างอื่น)
  }
  if (selected === 'ราคาเฉลี่ย/ตร.ม.') {
    return '(หน่วย : บาท / ตร.ม.)'; // (คุณอาจต้องปรับถ้าหน่วยเป็นอย่างอื่น)
  }

  // (!!!) ค่าเริ่มต้น เมื่อไม่ได้เลือกอะไร (หรือถ้าต้องการให้แสดงทุกหน่วย)
  return '(หน่วย : ล้านบาท)'; // กลับไปใช้ค่าเดิม
});
</script>

<template>
  <v-row>
    <v-snackbar
      v-model="showSnackbar"
      :timeout="snackbarTimeout"
      :color="snackbarColor"
      location="top end"
      variant="elevated"
    >
      {{ snackbarText }}
      <template v-slot:actions>
        <v-btn
          color="white"
          variant="text"
          @click="showSnackbar = false"
        >
          ปิด
        </v-btn>
      </template>
    </v-snackbar>

    <v-col cols="12">
      <div class="mt-3 mb-6">
        <div class="d-flex justify-space-between">
          <div class="d-flex py-0 align-center">
            <div>
              <h3 class="text-h5 card-title">รายงานแบ่งตามไตรมาส</h3>
              <ul class="v-breadcrumbs v-breadcrumbs--density-default text-subtitle-1 textSecondary pa-0 ml-n1">
                <li class="v-breadcrumbs-item"><a class="v-breadcrumbs-item--link" href="#">
                    <p>หน้าแรก</p>
                  </a></li>
                <li class="v-breadcrumbs-divider"><i class="mdi-chevron-right mdi v-icon"></i></li>
                <li class="v-breadcrumbs-item"><a class="v-breadcrumbs-item--link" href="#">
                    <p>รายงานแบ่งตามไตรมาส</p>
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
                            <v-col cols="12" md="6">
                                 <v-select v-model="selectedYear" label="เลือกปี" :items="yearOptions" class="mr-4"></v-select>
                
                            </v-col>
                            <v-col cols="12" md="6">
                               <v-select v-model="selectedRegion" label="เลือกพื้นที่" :items="regions" class="mr-4"></v-select>
                            </v-col>
                            
                        </v-row>
                    </v-card-text>
                </v-card>
            </v-col>
       

    <v-col cols="12" sm="12" lg="12">
      <div class="v-row">
        <div v-for="label in dataTypes" :key="label" class="v-col-sm-6 v-col-lg-3 v-col-12 py-0 mb-3">
          <div class="v-card v-theme--BLUE_THEME v-card--density-default elevation-10 rounded-md v-card--variant-elevated"
            @click="highlightRow(label)" 
            style="cursor: pointer;" 
            hover
            :class="{ 'card-is-active': selectedHighlight === label }"
          >
            <div class="v-card-text pa-5">
              <div class="d-flex align-center ga-4">
                <button type="button"
                  class="v-btn v-btn--elevated v-btn--icon v-theme--BLUE_THEME v-btn--density-default v-btn--size-default v-btn--variant-elevated"
                  :class="{ 'bg-primary': label === 'จำนวนหลัง', 'bg-secondary': label === 'มูลค่ารวม', 'bg-error': label === 'พื้นที่ใช้สอย', 'bg-warning': label === 'ราคาเฉลี่ย/ตร.ม.' }"
                  dark>
                  
                  <svg v-if="label === 'จำนวนหลัง'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 12.204c0-2.289 0-3.433.52-4.381c.518-.949 1.467-1.537 3.364-2.715l2-1.241C9.889 2.622 10.892 2 12 2s2.11.622 4.116 1.867l2 1.241c1.897 1.178 2.846 1.766 3.365 2.715S22 9.915 22 12.203v1.522c0 3.9 0 5.851-1.172 7.063S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.212S2 17.626 2 13.725z" /><path stroke-linecap="round" d="M12 15v3" /></g></svg>
                  <svg v-else-if="label === 'มูลค่ารวม'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 14c0-3.771 0-5.657 1.172-6.828S6.229 6 10 6h4c3.771 0 5.657 0 6.828 1.172S22 10.229 22 14s0 5.657-1.172 6.828S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.172S2 17.771 2 14Zm14-8c0-1.886 0-2.828-.586-3.414S13.886 2 12 2s-2.828 0-3.414.586S8 4.114 8 6" /><path stroke-linecap="round" d="M12 17.333c1.105 0 2-.746 2-1.666S13.105 14 12 14s-2-.746-2-1.667c0-.92.895-1.666 2-1.666m0 6.666c-1.105 0-2-.746-2-1.666m2 1.666V18m0-8v.667m0 0c1.105 0 2 .746 2 1.666" /></g></svg>
                  <svg v-else-if="label === 'พื้นที่ใช้สอย'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"><path d="M11 2c-4.055.007-6.178.107-7.536 1.464C2 4.928 2 7.285 2 11.999s0 7.071 1.464 8.536C4.93 21.999 7.286 21.999 12 21.999s7.071 0 8.535-1.464c1.358-1.357 1.457-3.48 1.464-7.536" /><path stroke-linejoin="round" d="m13 11l9-9m0 0h-5.344M22 2v5.344M21 3l-9 9m0 0h4m-4 0V8" /></g></svg>
                  <svg v-else-if="label === 'ราคาเฉลี่ย/ตร.ม.'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4.979 9.685C2.993 8.891 2 8.494 2 8s.993-.89 2.979-1.685l2.808-1.123C9.773 4.397 10.767 4 12 4s2.227.397 4.213 1.192l2.808 1.123C21.007 7.109 22 7.506 22 8s-.993.89-2.979 1.685l-2.808 1.124C14.227 11.603 13.233 12 12 12s-2.227-.397-4.213-1.191z" /><path d="m5.766 10l-.787.315C2.993 11.109 2 11.507 2 12s.993.89 2.979 1.685l2.808 1.124C9.773 15.603 10.767 16 12 16s2.227-.397 4.213-1.191l2.808-1.124C21.007 12.891 22 12.493 22 12s-.993-.89-2.979-1.685L18.234 10" /><path d="m5.766 14l-.787.315C2.993 15.109 2 15.507 2 16s.993.89 2.979 1.685l2.808 1.124C9.773 19.603 10.767 20 12 20s2.227-.397 4.213-1.192l2.808-1.123C21.007 16.891 22 16.494 22 16c0-.493-.993-.89-2.979-1.685L18.234 14" /></g></svg>
                </button>
                <div class="">
                  <h2 class="text-h4">{{ getValue(label) }}</h2>
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
          
          <v-row align="start">
            
            <v-col cols="12" md="8">
              <h3 class="card-title mb-1">
                รายงานแบ่งตามไตรมาส ประจำปี {{ selectedYear }} (พื้นที่: {{ selectedRegion }})
              </h3>
             <h5 class="card-subtitle" style="text-align: left;">{{ tableUnitSubtitle }}</h5>
            </v-col>

            <v-col cols="12" md="4">
              
              <v-row v-if="!loading && previousVisibleQuarterKey" align="center" justify="end" class="mt-0"> <v-col cols="auto" class="text-right">
                  <h3 class="card-title" :class="getPercentageColor(dynamicStatData.percent)"
                    style="font-size: 1.25rem;">
                    {{ formatPercentage(dynamicStatData.percent) }}
                  </h3>
                  <h5 class="card-subtitle text-grey-darken-1"> QOQ % ({{ dynamicStatData.label }}) </h5>
                </v-col>
              </v-row>

              <div v-else-if="!loading && !previousVisibleQuarterKey" class="text-right text-grey mt-2">
                <h5 class="card-subtitle text-grey-darken-1">(Q1 ไม่มีข้อมูล QOQ ให้เปรียบเทียบ)</h5>
              </div>
            
            </v-col>
          </v-row>
          <div class="mt-5">
            <apexchart height="350" :options="computedChartOptions" :series="finalChartSeries"></apexchart>
          </div>
        </v-card-text>
      </VCard>
    </v-col>


    


    <v-col cols="12">
      <VCard elevation="10" style="position: relative;">
      
        <v-overlay
            :model-value="exportLoading"
            class="align-center justify-center"
            persistent
            scrim="white"
            style="opacity: 0.8;"
          >
          <div class="text-center">
            <v-progress-circular
              color="primary"
              indeterminate
              size="64"
            ></v-progress-circular>
            <h4 class="text-primary mt-3">กำลังสร้างไฟล์รายงาน...</h4>
          </div>
        </v-overlay>
      
        <v-card-text>
          <div class="v-row mt-1">
            <div class="v-col-md-8 v-col-12">
              <div class="d-flex align-center">
                <div>
                  <h3 class="card-title mb-1">ตารางแบ่งตามไตรมาส ประจำปี {{ selectedYear }} (พื้นที่: {{ selectedRegion
                    }})</h3>
                  <h5 class="card-subtitle" style="text-align: left;">{{ tableUnitSubtitle }}</h5>
                </div>
              </div>
            </div>

            <div class="v-col-md-4 v-col-12 text-right">
              <div class="d-flex  justify-end v-col-md-12 v-col-lg-12 v-col-12 ">

                <v-btn class="text-primary v-btn--size-large" @click="exportToExcel" prepend-icon="mdi-file-excel" color="success">
                  <div class="text-none font-weight-regular">Export to Excel</div>
                </v-btn>
                
                <v-btn class="ml-2 v-btn--size-large" @click="exportToPDF" prepend-icon="mdi-file-pdf-box" color="error">
                  <div class="text-none font-weight-regular">Export to PDF</div>
                </v-btn>
                
              </div>
            </div>
          </div>
          
          <div class="v-table v-theme--BLUE_THEME v-table--density-default month-table mt-6">
            <div class="v-table__wrapper">
              <table id="table-to-export">
                <thead style="background-color: #F5F5F5;">
                  <tr>
                    <th class="text-h6">Financial Summary</th>
                    <th class="text-h6" :colspan="visibleQuarters.length + 1"
                      style="text-align: center; border-bottom: 2px solid #00A6D4;">{{ selectedYear }}</th>

                  </tr>
                  <tr>
                    <th class="text-subtitle-1" style="font-weight: 400;">(Unit: THB million)</th>
                    <th v-for="q in visibleQuarters" :key="q" class="text-h6 text-right" style="min-width: 160px;">{{ q }}</th>
                    <th class="text-h6 text-right" style="min-width: 160px;">{{ selectedYear }} (YTD)</th>

                  </tr>
                </thead>
                
                <tbody>
                  <template v-for="label in priceRanges" :key="label">
                    
                    <tr style="background-color: #fcf8ff;">
                      <td :colspan="visibleQuarters.length + 2"> 
                        <h6 class="text-p" style="font-size: 14px; font-weight: 600; color: #725AF2;">{{ label }}</h6>
                      </td>
                    </tr>

                    <tr v-for="type in dataTypes" 
                        :key="label + '-' + type" 
                        :style="getHighlightStyle(type)"
                        v-show="isRowVisible(type)" 
                    >
                      <td>
                        <h6 class="text-subtitle-1">{{ type }}</h6>
                      </td>
                      
                      <td v-for="q in visibleQuarters" :key="type + '-' + label + '-' + q" class="text-right" style="min-width: 160px; vertical-align: middle;">
                        <div>
                          <h6 class="text-subtitle-1 d-inline">
                            {{ formatNumber(getSpecificMetric(q.split(' ')[0], label, typeMap[type]), type !== 'จำนวนหลัง') }}
                          </h6>
                          
                          <span v-if="q.split(' ')[0] !== 'Q1'" 
                                class="text-caption d-inline ml-2" 
                                :class="getQOQPercentageColor(getQOQCellData(label, q, typeMap[type]))"
                                style="font-weight: 400; "
                          >
                            ({{ formatPercentage(getQOQCellData(label, q, typeMap[type])) }})
                          </span>
                        </div>
                      </td>
                      
                      <td :style="[{ backgroundColor: '#FFF3E0' }, getHighlightStyle(type)]" class="text-right" style="vertical-align: middle;">
                        <div>
                          <h6 class="text-subtitle-1" style="font-weight: 600;"> {{ getRowTotal(label, typeMap[type]) }}
                          </h6>
                          <span 
                            class="text-caption" :class="getQOQPercentageColor(getRowTotalYoY(label, typeMap[type]))"
                            style="font-weight: 400;"
                          >
                            ({{ formatPercentage(getRowTotalYoY(label, typeMap[type])) }})
                          </span>
                        </div>
                      </td>
                    </tr>
                  </template>

                  <tr :style="[{ backgroundColor: '#fcf8ff' }, getHighlightStyle('จำนวนหลัง')]" v-show="isRowVisible('จำนวนหลัง')">
                    <td>
                      <h6 class="text-subtitle-1" style="font-weight: 600; color: #F8285A;">จำนวนหลัง (รวม)</h6>
                    </td>
                    
                    <td v-for="q in visibleQuarters" :key="'total-unit-' + q" class="text-right" style="vertical-align: middle;">
                      <div>
                        <h6 class="text-subtitle-1" style="font-weight: 600; color: #F8285A;"> {{ getQuarterTotal(q, 'unit') }}
                        </h6>
                        <span v-if="q.split(' ')[0] !== 'Q1'" 
                              class="text-caption" :class="getQOQPercentageColor(getQuarterTotalQOQ(q, 'unit'))"
                              style="font-weight: 400;"
                        >
                          ({{ formatPercentage(getQuarterTotalQOQ(q, 'unit')) }})
                        </span>
                      </div>
                    </td>
                    
                    <td :style="[{ backgroundColor: '#FFF3E0' }, getHighlightStyle('จำนวนหลัง')]" class="text-right" style="vertical-align: middle;">
                      <div>
                        <h6 class="text-subtitle-1" style="font-weight: 800; color: #F8285A;"> {{ getTotalYearTotal('unit') }}
                        </h6>
                        <span 
                          class="text-caption" :class="getQOQPercentageColor(getGrandTotalYoY('unit'))"
                          style="font-weight: 600; color: #F8285A;"
                        >
                          ({{ formatPercentage(getGrandTotalYoY('unit')) }})
                        </span>
                      </div>
                    </td>
                  </tr>
                  
                  <tr :style="[{ backgroundColor: '#fcf8ff' }, getHighlightStyle('มูลค่ารวม')]" v-show="isRowVisible('มูลค่ารวม')">
                    <td>
                      <h6 class="text-subtitle-1" style="font-weight: 600; color: #F8285A;">มูลค่ารวม (รวม)</h6>
                    </td>
                    
                    <td v-for="q in visibleQuarters" :key="'total-value-' + q" class="text-right" style="vertical-align: middle;">
                       <div>
                        <h6 class="text-subtitle-1" style="font-weight: 600; color: #F8285A;"> {{ getQuarterTotal(q, 'value') }}
                        </h6>
                         <span v-if="q.split(' ')[0] !== 'Q1'" 
                              class="text-caption" :class="getQOQPercentageColor(getQuarterTotalQOQ(q, 'value'))"
                              style="font-weight: 400;"
                        >
                          ({{ formatPercentage(getQuarterTotalQOQ(q, 'value')) }})
                        </span>
                      </div>
                    </td>
                    
                    <td :style="[{ backgroundColor: '#FFF3E0' }, getHighlightStyle('มูลค่ารวม')]" class="text-right" style="vertical-align: middle;">
                      <div>
                        <h6 class="text-subtitle-1" style="font-weight: 800; color: #F8285A;"> {{ getTotalYearTotal('value') }}
                        </h6>
                        <span 
                          class="text-caption" :class="getQOQPercentageColor(getGrandTotalYoY('value'))"
                          style="font-weight: 600; color: #F8285A;"
                        >
                          ({{ formatPercentage(getGrandTotalYoY('value')) }})
                        </span>
                      </div>
                    </td>
                  </tr>
                  
                  <tr :style="[{ backgroundColor: '#fcf8ff' }, getHighlightStyle('พื้นที่ใช้สอย')]" v-show="isRowVisible('พื้นที่ใช้สอย')">
                    <td>
                      <h6 class="text-subtitle-1" style="font-weight: 600; color: #F8285A;">พื้นที่ใช้สอย (รวม)</h6>
                    </td>
                    
                    <td v-for="q in visibleQuarters" :key="'total-area-' + q" class="text-right" style="vertical-align: middle;">
                      <div>
                        <h6 class="text-subtitle-1" style="font-weight: 600; color: #F8285A;"> {{ getQuarterTotal(q, 'area') }}
                        </h6>
                         <span v-if="q.split(' ')[0] !== 'Q1'" 
                              class="text-caption" :class="getQOQPercentageColor(getQuarterTotalQOQ(q, 'area'))"
                              style="font-weight: 400;"
                        >
                          ({{ formatPercentage(getQuarterTotalQOQ(q, 'area')) }})
                        </span>
                      </div>
                    </td>
                    
                    <td :style="[{ backgroundColor: '#FFF3E0' }, getHighlightStyle('พื้นที่ใช้สอย')]" class="text-right" style="vertical-align: middle;">
                      <div>
                        <h6 class="text-subtitle-1" style="font-weight: 800; color: #F8285A;"> {{ getTotalYearTotal('area') }}
                        </h6>
                        <span 
                          class="text-caption" :class="getQOQPercentageColor(getGrandTotalYoY('area'))"
                          style="font-weight: 600; color: #F8285A;"
                        >
                          ({{ formatPercentage(getGrandTotalYoY('area')) }})
                        </span>
                      </div>
                    </td>
                  </tr>
                  
                  <tr :style="[{ backgroundColor: '#fcf8ff' }, getHighlightStyle('ราคาเฉลี่ย/ตร.ม.')]" v-show="isRowVisible('ราคาเฉลี่ย/ตร.ม.')">
                    <td>
                      <h6 class="text-subtitle-1" style="font-weight: 600; color: #F8285A;">ราคาเฉลี่ย/ตร.ม. (รวม)</h6>
                    </td>
                    
                    <td v-for="q in visibleQuarters" :key="'total-avg-' + q" class="text-right" style="vertical-align: middle;">
                      <div>
                        <h6 class="text-subtitle-1" style="font-weight: 600; color: #F8285A;"> {{ getQuarterAvgPrice(q) }}
                        </h6>
                        <span v-if="q.split(' ')[0] !== 'Q1'" 
                              class="text-caption" :class="getQOQPercentageColor(getQuarterAvgPriceQOQ(q))"
                              style="font-weight: 400;"
                        >
                          ({{ formatPercentage(getQuarterAvgPriceQOQ(q)) }})
                        </span>
                      </div>
                    </td>
                    
                    <td :style="[{ backgroundColor: '#FFF3E0' }, getHighlightStyle('ราคาเฉลี่ย/ตร.ม.')]" class="text-right" style="vertical-align: middle;">
                      <div>
                        <h6 class="text-subtitle-1" style="font-weight: 800; color: #F8285A;"> {{ getTotalYearAvgPrice() }}
                        </h6>
                         <span 
                          class="text-caption" :class="getQOQPercentageColor(getGrandTotalYoY('price_per_sqm'))"
                          style="font-weight: 600; color: #F8285A;"
                        >
                          ({{ formatPercentage(getGrandTotalYoY('price_per_sqm')) }})
                        </span>
                      </div>
                    </td>
                  </tr>
                  </tbody>
                </table>
            </div>
          </div>
        </v-card-text>
      </VCard>
    </v-col>
  </v-row>

</template>

<style scoped>
/* สั่งให้ v-card ที่เราคลิกได้ มี transition ที่นุ่มนวล */
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

/* (โค้ด v-tab ที่คุณอาจมีจากไฟล์อื่น - ใส่ไว้เผื่อ) */
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
</style>