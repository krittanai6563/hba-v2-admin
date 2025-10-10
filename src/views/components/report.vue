<script setup lang="ts">
import vuetify from '@/plugins/vuetify';
import MainRoutes from '@/router/MainRoutes';
import { isObject } from 'highcharts';
import { ref, computed, onMounted } from 'vue';
import { BottleOffIcon, CircleTriangleIcon, ManIcon, Rotate2Icon } from 'vue-tabler-icons';

interface SummaryData {
    yearly_data: Record<string, Record<string, number>>;
    quarterly_data: Record<string, Record<string, Record<string, number>>>;
    yearly_data_quarterly?: Record<string, Record<string, Record<string, number>>>;
    yearly_data_quarterly_region?: Record<string, Record<string, number>>;
    monthly_data?: Record<string, Record<string, Record<string, number>>>;
    monthly_member_summary?: Array<{
        month_number: number;
        total_members: number;
        submitted_members: number;
        not_submitted_members: number;
        year: string;
    }>;
    monthly_member_summary_compare_year?: Array<{
        month_number: number;
        total_members: number;
        submitted_members: number;
        not_submitted_members: number;
        year: string;
    }>;
}

const years = ['2567', '2568'];
const months = [
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

const Quarter = ['Q1', 'Q2', 'Q3', 'Q4'];

const comparisonTypes = ['เปรียบเทียบเดือน', 'เปรียบเทียบปี', 'เปรียบเทียบไตรมาส'];

const selectedComparisonPeriods = computed(() => {
    if (selectedComparisonType.value === 'เปรียบเทียบปี') {
        return years;
    } else if (selectedComparisonType.value === 'เปรียบเทียบไตรมาส') {
        return ['ไตรมาส 1', 'ไตรมาส 2', 'ไตรมาส 3', 'ไตรมาส 4'];
    } else if (selectedComparisonType.value === 'เปรียบเทียบเดือน') {
        return months;
    }
    return [];
});

const selectedYear = ref(''); 
const selectedMonth = ref(''); 
const selectedComparisonQuarter = ref(''); 
const selectedComparisonType = ref(''); 
const selectedComparisonPeriod = ref(''); 

const currentYear = new Date().getFullYear() + 543;

const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user';

interface SummaryData {
    yearly_data: Record<string, Record<string, number>>;
    quarterly_data: Record<string, Record<string, Record<string, number>>>;
}

const summaryData = ref<SummaryData>({
    yearly_data: {},
    quarterly_data: {}
});

const chartYears = computed(() => {
    return ['2567', '2568'];
});

const isLoading = ref(true);

const fetchSummary = async () => {
    if (userRole !== 'admin' && !userId) return;
    isLoading.value = true;

    try {
        const res = await fetch(`https://uat.hba-sales.org/backend/roport_pricerange.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: userId,
                role: userRole,
                year: currentYear.toString()
            })
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        console.log('Fetched Summary Data:', data);

        if (data && data.yearly_data && data.quarterly_data) {
            summaryData.value = data;
        } else {
            throw new Error('Incomplete data received');
        }
    } catch (err) {
        console.error('Error fetching summary:', err);
        summaryData.value = { yearly_data: {}, quarterly_data: {}, yearly_data_quarterly: {} };
    } finally {
        isLoading.value = false;
    }
};

onMounted(async () => {
    await fetchSummary();
    console.log('Summary Data:', summaryData.value);
});

const calculatePercentageDifference = (value2567: number, value2568: number) => {
    if (value2567 === 0) {
        return value2568 === 0 ? 0 : 100;
    }
    return ((value2568 - value2567) / value2567) * 100;
};

const series = computed(() => {
    const priceRanges = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป'];

    if (selectedComparisonType.value === 'เปรียบเทียบไตรมาส') {
        return []; 
    }

    if (selectedComparisonType.value === 'เปรียบเทียบปี') {
        const year1 = selectedYear.value;
        const year2 = selectedComparisonPeriod.value; 

        const totalYear1 = priceRanges.reduce((total: number, range) => {
            const value = summaryData.value['yearly_data']?.[year1]?.[range] || 0; 
            return total + value;
        }, 0);

        const totalYear2 = priceRanges.reduce((total: number, range) => {
            const value = summaryData.value['yearly_data']?.[year2]?.[range] || 0; 
            return total + value;
        }, 0);

        const percentageDifference = priceRanges.map((range) => {
            const valueYear1 = summaryData.value['yearly_data']?.[year1]?.[range] || 0; 
            const valueYear2 = summaryData.value['yearly_data']?.[year2]?.[range] || 0; 
            return calculatePercentageDifference(valueYear1, valueYear2); 
        });

        const totalPercentageDifference = calculatePercentageDifference(totalYear1, totalYear2);

        return [
            {
                name: `ปี ${year1}`,
                type: 'column',
                data: [
                    ...priceRanges.map((range) => summaryData.value['yearly_data']?.[year1]?.[range] || 0), 
                    totalYear1
                ],
                color: '#008FFB'
            },
            {
                name: `ปี ${year2}`,
                type: 'column',
                data: [
                    ...priceRanges.map((range) => summaryData.value['yearly_data']?.[year2]?.[range] || 0), 
                    totalYear2
                ],
                color: '#00E396'
            },
            {
                name: 'เปอร์เซ็นต์การเปลี่ยนแปลง',
                type: 'line',
                data: [...percentageDifference, totalPercentageDifference],
                color: '#FF4560',
                dataLabels: {
                    enabled: true,
                    formatter: function (val: number) {
                        return `${val.toFixed(2)}%`;
                    }
                }
            }
        ];
    }

    return [];
});

const chartOptions = computed(() => ({
    chart: {
        height: 350,
        type: 'line',
        stacked: false,
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
        toolbar: { show: false }
    },
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '90%',
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
        categories: ['ไม่เกิน 2.50 ล้านบ้าน', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20 ล้านบาท', 'รวม']
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
}));

const monthsInQuarter = computed(() => {
    const quarter = selectedComparisonQuarter.value;
    switch (quarter) {
        case 'Q1':
            return ['มกราคม', 'กุมภาพันธ์', 'มีนาคม'];
        case 'Q2':
            return ['เมษายน', 'พฤษภาคม', 'มิถุนายน'];
        case 'Q3':
            return ['กรกฎาคม', 'สิงหาคม', 'กันยายน'];
        case 'Q4':
            return ['ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        default:
            return [];
    }
});

const monthNamesMapping: Record<string, string> = {
    January: 'มกราคม',
    February: 'กุมภาพันธ์',
    March: 'มีนาคม',
    April: 'เมษายน',
    May: 'พฤษภาคม',
    June: 'มิถุนายน',
    July: 'กรกฎาคม',
    August: 'สิงหาคม',
    September: 'กันยายน',
    October: 'ตุลาคม',
    November: 'พฤศจิกายน',
    December: 'ธันวาคม'
};

const series3 = computed(() => {
    const year1 = selectedYear.value; 
    const year2 = selectedComparisonPeriod.value; 
    const quarter = selectedComparisonQuarter.value;

    const quarterData1 = summaryData.value.quarterly_data[year1]?.[quarter] || {};
    const quarterData2 = summaryData.value.quarterly_data[year2]?.[quarter] || {};

    const translatedQuarterData1 = Object.fromEntries(
        Object.entries(quarterData1).map(([month, value]) => [monthNamesMapping[month] || month, value])
    );
    const translatedQuarterData2 = Object.fromEntries(
        Object.entries(quarterData2).map(([month, value]) => [monthNamesMapping[month] || month, value])
    );

    const totalData1 = monthsInQuarter.value.map((month) => translatedQuarterData1[month] || 0);
    const totalData2 = monthsInQuarter.value.map((month) => translatedQuarterData2[month] || 0);

    const percentageChangesPerMonth = monthsInQuarter.value.map((month) => {
        const value1 = translatedQuarterData1[month] || 0;
        const value2 = translatedQuarterData2[month] || 0;
        return calculatePercentageDifference(value1, value2);
    });

    const data1 = monthsInQuarter.value.map((month) => translatedQuarterData1[month] || 0);
    const data2 = monthsInQuarter.value.map((month) => translatedQuarterData2[month] || 0);

    const totalPercentageChange = calculatePercentageDifference(
        totalData1.reduce((a, b) => a + b, 0),
        totalData2.reduce((a, b) => a + b, 0)
    );
    return [
        {
            name: `ไตรมาส ${quarter.replace('Q', '')} ปี ${year1}`,
            type: 'column',
            data: [...data1, totalData1.reduce((a, b) => a + b, 0)]
        },
        {
            name: `ไตรมาส ${quarter.replace('Q', '')} ปี ${year2}`,
            type: 'column',
            data: [...data2, totalData2.reduce((a, b) => a + b, 0)]
        },
        {
            name: 'เปอร์เซ็นต์การเปลี่ยนแปลง ',
            type: 'line',
            data: [...percentageChangesPerMonth, totalPercentageChange],
            color: '#FF4560',
            dataLabels: {
                enabled: true,
                formatter: function (val: number) {
                    return `${val.toFixed(2)}%`;
                }
            }
        }
    ];
});

const chartOptions3 = computed(() => ({
    chart: {
        height: 300,
        width: '100%',
        type: 'line',
        stacked: false,
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
        toolbar: { show: false }
    },
    plotOptions: {
        line: {
            dataLabels: {
                enabled: true,
                position: 'top',
                offsetY: -20
            },
            curve: 'smooth'
        }
    },
    dataLabels: {
        enabled: true,
        position: 'top',
        offsetY: -20,
        formatter: function (val: number) {
            return val.toLocaleString();
        },
        style: {
            fontSize: '10px'
        }
    },

    stroke: {
        width: 1,
        curve: 'smooth'
    },
    grid: {
        show: true,
        strokeDashArray: 4,
        borderColor: 'rgba(0, 0, 0, 0.1)'
    },
    xaxis: {
        categories: [...monthsInQuarter.value, 'รวม'],
        labels: {
            style: {
                colors: '#a1aab2'
            }
        }
    },
    yaxis: [
        {
            seriesName: 'Income',
            axisTicks: { show: false },
            labels: {
                show: false,
                style: { colors: '#00E396' }
            },
            tooltip: { enabled: false }
        },
        {
            seriesName: 'Cashflow',
            opposite: false,
            axisTicks: { show: false },
            labels: {
                show: false,
                style: { colors: '#FEB019' }
            }
        },
        {
            seriesName: 'Revenue',
            opposite: false,
            axisTicks: { show: false },
            labels: {
                show: false,
                style: { colors: '#008FFB' }
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
}));

const series4 = computed(() => {
    const year1 = selectedYear.value; 
    const year2 = selectedComparisonPeriod.value;
    const quarter = selectedComparisonQuarter.value;

    const quarterData1 = summaryData.value.yearly_data_quarterly?.[year1]?.[quarter] || {};
    const quarterData2 = summaryData.value.yearly_data_quarterly?.[year2]?.[quarter] || {};

    console.log('quarterData1:', quarterData1);
    console.log('quarterData2:', quarterData2);

    const priceRanges = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป'];


    const data1 = priceRanges.map((range) => quarterData1[range] || 0); 
    const data2 = priceRanges.map((range) => quarterData2[range] || 0);


    const percentageChanges = data1.map((value1, index) => {
        const value2 = data2[index] || 0;
        return calculatePercentageDifference(value1, value2);
    });


    const total1 = data1.reduce((a, b) => a + b, 0);
    const total2 = data2.reduce((a, b) => a + b, 0);
    const totalPercentageChange = calculatePercentageDifference(total1, total2);

    return [
        {
            name: `${year1} ไตรมาส ${quarter}`,
            type: 'column',
            data: [...data1, total1],
            color: '#008FFB'
        },
        {
            name: `${year2} ไตรมาส ${quarter}`,
            type: 'column',
            data: [...data2, total2],
            color: '#00E396'
        },
        {
            name: 'เปอร์เซ็นต์การเปลี่ยนแปลง',
            type: 'line',
            data: [...percentageChanges, totalPercentageChange],
            color: '#FF4560',
            dataLabels: {
                enabled: true,
                formatter: function (val: number) {
                    return `${val.toFixed(2)}%`;
                }
            }
        }
    ];
});

const chartOptions4 = computed(() => ({
    chart: {
        height: 250,
        type: 'line',
        stacked: false,
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
        toolbar: { show: false }
    },
    plotOptions: {
        bar: {
            borderRadius: 5,
            columnWidth: '55%',
            dataLabels: {
                position: 'top',
                offsetY: -20
            }
        }
    },
  dataLabels: {
    enabled: true,
    position: 'top',
    offsetY: -20,
    formatter: function (val: number) {  
        return val.toLocaleString(); 
    },
    style: {
        fontSize: '10px'
    }
},

    stroke: {
        width: [1, 1, 4]
    },
    grid: {
        show: true,
        strokeDashArray: 4,
        borderColor: 'rgba(0, 0, 0, 0.1)'
    },
    xaxis: {
        categories: ['ไม่เกิน 2.50 ล้านบ้าน', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20 ล้านบาท', 'รวม']
    },
    yaxis: [
        {
            seriesName: 'Income',
            axisTicks: { show: true },
            labels: {
                show: false,
                style: { colors: '#00E396' }
            },
            tooltip: { enabled: true }
        },
        {
            seriesName: 'Cashflow',
            opposite: true,
            axisTicks: { show: true },
            labels: {
                show: false,
                style: { colors: '#FEB019' }
            }
        },
        {
            seriesName: 'Revenue',
            opposite: true,
            axisTicks: { show: true },
            labels: {
                show: false,
                style: { colors: '#008FFB' }
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
}));

// const series64 = computed(() => {
//     const regions = ['กรุงเทพปริมณฑล', 'ภาคกลาง', 'ภาคตะวันตก', 'ภาคตะวันออก', 'ภาคตะวันออกเฉียงเหนือ', 'ภาคเหนือ', 'ภาคใต้'];

//     // Adjust the type to reflect the correct structure
//     const yearlyData: Record<string, Record<string, Record<string, number>>> = summaryData.value?.yearly_data_quarterly_region || {};

//     if (!yearlyData) {
//         console.error('Data for regions is missing');
//         return []; // Return empty if data is missing
//     }

//     // Use selectedYear and selectedComparisonPeriod to select the years for comparison
//     const year1 = selectedYear.value; // Selected year from the form
//     const year2 = selectedComparisonPeriod.value; // Selected comparison year from the form
//     const quarter = selectedComparisonQuarter.value; // Selected quarter from the form

//     // Fetch region data for the selected quarter in both years
//     const regionData1 = regions.map((region) => yearlyData[region]?.[year1]?.[quarter] || 0); // Data for the first year and selected quarter
//     const regionData2 = regions.map((region) => yearlyData[region]?.[year2]?.[quarter] || 0); // Data for the second year and selected quarter

//     // Calculate percentage change between the years
//     const percentageChanges = regions.map((region, index) => {
//         const value1 = regionData1[index];
//         const value2 = regionData2[index];
//         return calculatePercentageDifference(value1, value2);
//     });

//     // Return the series data for the chart
//     return [
//         {
//             name: `ปี ${year1}`,
//             type: 'column',
//             data: regionData1,
//             color: '#003366' // Dark blue
//         },
//         {
//             name: `ปี ${year2}`,
//             type: 'column',
//             data: regionData2,
//             color: '#FFEB3B' // Yellow
//         },
//         {
//             name: 'เปอร์เซ็นต์การเปลี่ยนแปลง',
//             type: 'line',
//             data: percentageChanges,
//             color: '#4CAF50', // Green
//             dataLabels: {
//                 enabled: true,
//                 formatter: function (val) {
//                     return `${val.toFixed(2)}%`;
//                 }
//             }
//         }
//     ];
// });

// const chartOptions44 = computed(() => ({
//     chart: {
//         height: 350,
//         type: 'line', // Use line chart for showing percentage change
//         stacked: false,
//         fontFamily: 'inherit',
//         foreColor: '#adb0bb',
//         toolbar: { show: false }
//     },
//     plotOptions: {
//         bar: {
//             borderRadius: 5,
//             columnWidth: '55%',
//             dataLabels: {
//                 position: 'top',
//                 offsetY: -20
//             }
//         }
//     },
//     dataLabels: {
//         enabled: true,
//         position: 'top',
//         offsetY: -20,
//         formatter: function (val) {
//             return val.toLocaleString();
//         },
//         style: {
//             fontSize: '10px'
//         }
//     },
//     stroke: {
//         width: [1, 1, 4]
//     },
//     grid: {
//         show: true,
//         strokeDashArray: 4,
//         borderColor: 'rgba(0, 0, 0, 0.1)'
//     },
//     xaxis: {
//         categories: ['กรุงเทพปริมณฑล', 'ภาคกลาง', 'ภาคตะวันตก', 'ภาคตะวันออก', 'ภาคตะวันออกเฉียงเหนือ', 'ภาคเหนือ', 'ภาคใต้']
//     },
//     yaxis: [
//         {
//             seriesName: 'Income',
//             axisTicks: { show: true },
//             labels: {
//                 show: false,
//                 style: { colors: '#00E396' }
//             },
//             tooltip: { enabled: true }
//         },
//         {
//             seriesName: 'Cashflow',
//             opposite: false,
//             axisTicks: { show: false },
//             labels: {
//                 show: false,
//                 style: { colors: '#FEB019' }
//             }
//         },
//         {
//             seriesName: 'Revenue',
//             opposite: true,
//             axisTicks: { show: true },
//             labels: {
//                 show: false,
//                 style: { colors: '#008FFB' }
//             }
//         }
//     ],
//     tooltip: {
//         fixed: {
//             enabled: true,
//             position: 'topLeft',
//             offsetY: 0,
//             offsetX: 0
//         }
//     },
//     legend: {
//         horizontalAlign: 'center',
//         offsetX: 0
//     }
// }));

// const chartOptions44 = computed(() => ({
//     chart: {
//         height: 350,
//         type: 'line', // ใช้กราฟเส้นสำหรับการแสดงเปอร์เซ็นต์การเปลี่ยนแปลง
//         stacked: false,
//         fontFamily: 'inherit',
//         foreColor: '#adb0bb',
//         toolbar: { show: false }
//     },
//     plotOptions: {
//         bar: {
//             borderRadius: 5,
//             columnWidth: '55%',
//             dataLabels: {
//                 position: 'top',
//                 offsetY: -20
//             }
//         }
//     },
//     dataLabels: {
//         enabled: true,
//         position: 'top',
//         offsetY: -20,
//         formatter: function (val) {
//             return val.toLocaleString();
//         },
//         style: {
//             fontSize: '10px'
//         }
//     },
//     stroke: {
//         width: [1, 1, 4]
//     },
//     grid: {
//         show: true,
//         strokeDashArray: 4,
//         borderColor: 'rgba(0, 0, 0, 0.1)'
//     },
//     xaxis: {
//         categories: ['กรุงเทพปริมณฑล', 'ภาคกลาง', 'ภาคตะวันตก', 'ภาคตะวันออก', 'ภาคตะวันออกเฉียงเหนือ', 'ภาคเหนือ', 'ภาคใต้']
//     },
//     yaxis: [
//         {
//             seriesName: 'Income',
//             axisTicks: { show: true },
//             labels: {
//                 show: false,
//                 style: { colors: '#00E396' }
//             },
//             tooltip: { enabled: true }
//         },
//         {
//             seriesName: 'Cashflow',
//             opposite: false,
//             axisTicks: { show: false },
//             labels: {
//                 show: false,
//                 style: { colors: '#FEB019' }
//             }
//         },
//         {
//             seriesName: 'Revenue',
//             opposite: true,
//             axisTicks: { show: true },
//             labels: {
//                 show: false,
//                 style: { colors: '#008FFB' }
//             }
//         }
//     ],
//     tooltip: {
//         fixed: {
//             enabled: true,
//             position: 'topLeft',
//             offsetY: 0,
//             offsetX: 0
//         }
//     },
//     legend: {
//         horizontalAlign: 'center',
//         offsetX: 0
//     }
// }));

// const memberSeries = computed(() => {
//     const data = summaryData.value.monthly_member_summary || [];
//     const compareYearData = summaryData.value.monthly_member_summary_compare_year || [];

//     // ตรวจสอบว่ามีข้อมูลหรือไม่
//     if (!data.length || !compareYearData.length) {
//         console.error('No data available for the selected year and comparison year');
//         return [];
//     }

//     // กำหนดค่าปีและไตรมาสแบบตายตัว
//     const selectedYear = '2568'; // กำหนดปีที่ต้องการเปรียบเทียบ
//     const selectedComparisonQuarter = 'Q2'; // กำหนดไตรมาสที่ต้องการเปรียบเทียบ

//     const quarters = {
//         Q1: [1, 2, 3],
//         Q2: [4, 5, 6],
//         Q3: [7, 8, 9],
//         Q4: [10, 11, 12]
//     };

//     const monthsInQuarter = quarters[selectedComparisonQuarter];

//     // สร้างกราฟสำหรับปีที่เลือกและปีเปรียบเทียบ
//     const yearTotalMembers = monthsInQuarter.map(
//         (month) => parseInt(data.find((item) => item.month_number === month && item.year === selectedYear)?.total_members || 0) // สมาชิกทั้งหมด
//     );

//     const yearSubmittedMembers = monthsInQuarter.map(
//         (month) => parseInt(data.find((item) => item.month_number === month && item.year === selectedYear)?.submitted_members || 0) // สมาชิกที่กรอกข้อมูล
//     );

//     const yearNotSubmittedMembers = monthsInQuarter.map(
//         (month) => parseInt(data.find((item) => item.month_number === month && item.year === selectedYear)?.not_submitted_members || 0) // สมาชิกที่ยังไม่ได้กรอกข้อมูล
//     );

//     // เปรียบเทียบข้อมูลปี
//     const compareYearTotalMembers = monthsInQuarter.map((month) =>
//         parseInt(compareYearData.find((item) => item.month_number === month && item.year === selectedYear + 1)?.total_members || 0)
//     );

//     const compareYearSubmittedMembers = monthsInQuarter.map((month) =>
//         parseInt(compareYearData.find((item) => item.month_number === month && item.year === selectedYear + 1)?.submitted_members || 0)
//     );

//     const compareYearNotSubmittedMembers = monthsInQuarter.map((month) =>
//         parseInt(compareYearData.find((item) => item.month_number === month && item.year === selectedYear + 1)?.not_submitted_members || 0)
//     );

//     // กำหนดชื่อสำหรับกราฟที่แสดงปีและไตรมาส
//     const yearLabel = `ปี ${selectedYear} ไตรมาส ${selectedComparisonQuarter}`;
//     const compareYearLabel = `ปี ${parseInt(selectedYear) + 1} ไตรมาส ${selectedComparisonQuarter}`; // ปีเปรียบเทียบ

//     return [
//         {
//             name: `${yearLabel} สมาชิกทั้งหมด`,
//             type: 'column',
//             data: yearTotalMembers,
//             color: '#008FFB'
//         },
//         {
//             name: `${yearLabel} สมาชิกที่กรอกข้อมูล`,
//             type: 'column',
//             data: yearSubmittedMembers,
//             color: '#00E396'
//         },
//         {
//             name: `${yearLabel} สมาชิกที่ยังไม่ได้กรอก`,
//             type: 'column',
//             data: yearNotSubmittedMembers,
//             color: '#FF4560'
//         },
//         {
//             name: `${compareYearLabel} สมาชิกทั้งหมด`,
//             type: 'column',
//             data: compareYearTotalMembers,
//             color: '#7E7E7E'
//         },
//         {
//             name: `${compareYearLabel} สมาชิกที่กรอกข้อมูล`,
//             type: 'column',
//             data: compareYearSubmittedMembers,
//             color: '#FFD700'
//         },
//         {
//             name: `${compareYearLabel} สมาชิกที่ยังไม่ได้กรอก`,
//             type: 'column',
//             data: compareYearNotSubmittedMembers,
//             color: '#F5A623'
//         }
//     ];
// });

// const chartOptionsMember = computed(() => ({
//     chart: {
//         type: 'bar',
//         height: 350,
//         stacked: false, // ตั้งค่าให้กราฟไม่ทับซ้อนกัน
//         fontFamily: 'inherit',
//         toolbar: { show: false }
//     },
//     plotOptions: {
//         bar: {
//             horizontal: false,
//             borderRadius: 4,
//             columnWidth: '35%' // ปรับความกว้างของกราฟแต่ละคอลัมน์
//         }
//     },
//     xaxis: {
//         // แสดงเดือนที่เกี่ยวข้องกับไตรมาสที่เลือก
//         categories: ['ม.ค.', 'ก.พ.', 'มี.ค.'] // เปลี่ยนให้แสดงแค่เดือนของไตรมาสที่เลือก (ตัวอย่าง: Q1)
//     },
//     dataLabels: { enabled: true },
//     legend: { position: 'top' },
//     tooltip: {
//         shared: true, // Enable shared tooltips
//         intersect: false // Disable intersect to avoid conflict with 'shared'
//     }
// }));

// const monthlyDataSeries = computed(() => {
//     const priceRanges = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป'];

//     const year1 = selectedYear.value; // The selected year for comparison (e.g., 2568)
//     const year2 = selectedComparisonPeriod.value; // The year to compare with (e.g., 2567)

//     // Fetch monthly data for the selected year and month
//     const monthDataYear1 = summaryData.value['monthly_data']?.[year1]?.[selectedMonth.value] || {};
//     const monthDataYear2 = summaryData.value['monthly_data']?.[year2]?.[selectedMonth.value] || {};

//     const totalYear1 = priceRanges.reduce((total: number, range) => {
//         const value = monthDataYear1[range] || 0;
//         return total + value;
//     }, 0);

//     const totalYear2 = priceRanges.reduce((total: number, range) => {
//         const value = monthDataYear2[range] || 0;
//         return total + value;
//     }, 0);

//     const percentageDifference = priceRanges.map((range) => {
//         const valueYear1 = monthDataYear1[range] || 0;
//         const valueYear2 = monthDataYear2[range] || 0;
//         return calculatePercentageDifference(valueYear1, valueYear2);
//     });

//     const totalPercentageDifference = calculatePercentageDifference(totalYear1, totalYear2);

//     return [
//         {
//             name: `ปี ${year1} เดือน ${selectedMonth.value}`,
//             type: 'column',
//             data: [
//                 ...priceRanges.map((range) => monthDataYear1[range] || 0), // Data for year1, selected month
//                 totalYear1
//             ],
//             color: '#008FFB'
//         },
//         {
//             name: `ปี ${year2} เดือน ${selectedMonth.value}`,
//             type: 'column',
//             data: [
//                 ...priceRanges.map((range) => monthDataYear2[range] || 0), // Data for year2, selected month
//                 totalYear2
//             ],
//             color: '#00E396'
//         },
//         {
//             name: 'เปอร์เซ็นต์การเปลี่ยนแปลง',
//             type: 'line',
//             data: [...percentageDifference, totalPercentageDifference],
//             color: '#FF4560',
//             dataLabels: {
//                 enabled: true,
//                 formatter: function (val: number) {
//                     return `${val.toFixed(2)}%`;
//                 }
//             }
//         }
//     ];
// });

// const monthlyDataChartOptions = computed(() => ({
//     chart: {
//         height: 350,
//         type: 'line',
//         stacked: false,
//         fontFamily: 'inherit',
//         foreColor: '#adb0bb',
//         toolbar: { show: false }
//     },
//     plotOptions: {
//         bar: {
//             borderRadius: 4,
//             columnWidth: '90%',
//             dataLabels: {
//                 position: 'top',
//                 offsetY: 0
//             }
//         },
//         line: {
//             dataLabels: {
//                 position: 'top',
//                 offsetY: 0
//             },
//             curve: 'smooth'
//         }
//     },
//     dataLabels: {
//         enabled: true,
//         position: 'top',
//         offsetY: -13,
//         style: {
//             fontSize: '10px'
//         }
//     },
//     stroke: {
//         width: [2, 2, 4],
//         curve: 'smooth'
//     },
//     grid: {
//         show: true,
//         strokeDashArray: 4,
//         borderColor: 'rgba(0, 0, 0, 0.1)'
//     },
//     xaxis: {
//         categories: ['ไม่เกิน 2.50 ล้านบ้าน', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20 ล้านบาท', 'รวม']
//     },
//     yaxis: [
//         {
//             seriesName: `ปี ${selectedYear.value}`,
//             axisTicks: { show: true },
//             labels: {
//                 show: false,
//                 style: { colors: '#008FFB' }
//             },
//             tooltip: { enabled: true }
//         },
//         {
//             seriesName: `ปี ${selectedComparisonPeriod.value}`,
//             opposite: true,
//             axisTicks: { show: true },
//             labels: {
//                 show: false,
//                 style: { colors: '#00E396' }
//             }
//         },
//         {
//             seriesName: 'เปอร์เซ็นต์การเปลี่ยนแปลง',
//             opposite: false,
//             axisTicks: { show: true },
//             labels: {
//                 show: false,
//                 style: { colors: '#FF4560' }
//             }
//         }
//     ],
//     tooltip: {
//         fixed: {
//             enabled: true,
//             position: 'topLeft',
//             offsetY: 0,
//             offsetX: 0
//         }
//     },
//     legend: {
//         horizontalAlign: 'center',
//         offsetX: 0
//     }
// }));
</script>

<template>
    <v-col cols="12">
        <div class="mt-3 mb-6">
            <div class="d-flex justify-space-between">
                <div class="d-flex py-0 align-center">
                    <div>
                        <h3 class="text-h5 card-title">รายงานเปรียบเทียบยอดเซ็นสัญญา</h3>
                        <ul class="v-breadcrumbs v-breadcrumbs--density-default text-subtitle-1 textSecondary pa-0 ml-n1">
                            <li class="v-breadcrumbs-item">
                                <a class="v-breadcrumbs-item--link" href="#">
                                    <p>หน้าแรก</p>
                                </a>
                            </li>
                            <li class="v-breadcrumbs-divider"><i class="mdi-chevron-right mdi v-icon"></i></li>
                            <li class="v-breadcrumbs-item">
                                <a class="v-breadcrumbs-item--link" href="#">
                                    <p>สรุปรายงาน</p>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </v-col>

    <v-row>
        <v-col cols="12">
            <v-card>
                <v-card-text>
                    <h3 class="card-title mb-1">เลือกข้อมูลที่ใช้สำหรับเปรียบเทียบยอดเซ็นสัญญา</h3>
                    <h5 class="card-subtitle">สามารถเลือกข้อมูลสำหรับการค้นหา</h5>
                    <br />

                    <v-row>
                        <!-- เลือกปี -->
                        <v-col cols="12" md="4">
                            <v-select
                                v-model="selectedYear"
                                :items="years"
                                label="เลือกปี"
                                item-text="year"
                                item-value="year"
                                outlined
                            ></v-select>
                        </v-col>

                        <!-- เลือกเดือน -->
                        <v-col cols="12" md="4">
                            <v-select
                                v-model="selectedMonth"
                                :items="months"
                                label="เลือกเดือน"
                                item-text="text"
                                item-value="value"
                                outlined
                            ></v-select>
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-select
                                v-model="selectedComparisonQuarter"
                                :items="Quarter"
                                label="เลือกไตรมาส"
                                item-text="text"
                                item-value="value"
                                outlined
                            />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-select
                                v-model="selectedComparisonType"
                                :items="comparisonTypes"
                                label="เลือกประเภทการเปรียบเทียบ"
                                outlined
                            ></v-select>
                        </v-col>

                        <!-- เลือกช่วงเวลาในการเปรียบเทียบ -->
                        <v-col cols="12" md="4">
                            <v-select
                                v-model="selectedComparisonPeriod"
                                :items="selectedComparisonPeriods"
                                label="เลือกช่วงเวลาในการเปรียบเทียบ"
                                :disabled="!selectedComparisonType"
                                outlined
                            ></v-select>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>
        </v-col>
    </v-row>

    <!-- <v-row v-if="selectedComparisonType !== 'เปรียบเทียบไตรมาส'">
        <v-col cols="12">
            <v-card>
                <v-card-text>
                    <div>
                        <h3 class="card-title">กราฟเปรียบเทียบยอดเซ็นสัญญา แยกตามราคาบ้าน</h3>
                        <h5 class="card-subtitle">เดือน {{ selectedMonth }} ปี ({{ selectedYear }} - {{ selectedComparisonPeriod }})</h5>
                    </div>
                    <apexchart height="350" :options="monthlyDataChartOptions" :series="monthlyDataSeries" />
                </v-card-text>
            </v-card>
        </v-col>
    </v-row> -->

    <!-- <v-row v-if="selectedComparisonType !== 'เปรียบเทียบไตรมาส'">
    <v-col cols="12">
      <VCard elevation="10">
        <v-card-text>
          <div class="d-flex justify-space-between align-center mb-4">
            <div>
              <h3 class="card-title">กราฟเปรียบเทียบยอดเซ็นสัญญา แยกตามราคาบ้าน</h3>
              <h5 class="card-subtitle">เดือน มกราคม ปี ({{ chartYears[0] }} - {{ chartYears[1] }}) </h5>
            </div>
          </div>
          <div v-if="isLoading" class="text-center text-secondary py-10">
            รอประมวลผลข้อมูลจากระบบ...
          </div>

          <apexchart height="350" :options="chartOptions" :series="series"></apexchart>
        </v-card-text>
      </VCard>
    </v-col>
  </v-row> -->
    <v-row>
        <v-col cols="12">
            <v-card>
                <v-card-text>
                    <h3 class="card-title mb-1">กราฟเปรียบเทียบยอดเซ็นสัญญา แยกตามไตรมาส</h3>
                    <!-- Dynamically update the subtitle with selected years and quarter -->
                    <h5 class="card-subtitle">
                        ไตรมาส {{ selectedComparisonQuarter }} ปี {{ selectedYear }} - {{ selectedComparisonPeriod }}
                    </h5>
                    <apexchart type="line" height="350" :options="chartOptions3" :series="series3"></apexchart>
                </v-card-text>
            </v-card>
        </v-col>
    </v-row>

    <v-row>
        <v-col cols="12">
            <v-card>
                <v-card-text>
                    <h3 class="card-title mb-1">กราฟเปรียบเทียบยอดเซ็นสัญญา แยกตามราคาบ้าน</h3>
                    <!-- Dynamically update the subtitle with selected quarter and years -->
                    <h5 class="card-subtitle">
                        ไตรมาส {{ selectedComparisonQuarter }} ปี {{ selectedYear }} - {{ selectedComparisonPeriod }}
                    </h5>
                    <apexchart height="350" :options="chartOptions4" :series="series4"></apexchart>
                </v-card-text>
            </v-card>
        </v-col>
    </v-row>

    <!-- <v-row>
        <v-col cols="12">
            <v-card>
                <v-card-text>
                    <h3 class="card-title mb-1">กราฟเปรียบเทียบยอดเซ็นสัญญา แยกตามภูมิภาค</h3>
            
                    <h5 class="card-subtitle">
                        ไตรมาส {{ selectedComparisonQuarter }} ปี {{ selectedYear }} และ ปี {{ selectedComparisonPeriod }}
                    </h5>
                    <apexchart height="350" :options="chartOptions44" :series="series64"></apexchart>
                   
                </v-card-text>
            </v-card>
        </v-col>
    </v-row> -->

    <!-- <v-row>
        <v-col cols="12">
            <v-card>
                <v-card-text>
                    <h3 class="card-title mb-1">กราฟแสดงจำนวนสมาชิกที่กรอกและไม่กรอกข้อมูลในแต่ละเดือน</h3>
                    <h5 class="card-subtitle">ข้อมูลจากปี {{ selectedYear }}</h5>
                    <apexchart height="350" :options="chartOptionsMember" :series="memberSeries" />
                </v-card-text>
            </v-card>
        </v-col>
    </v-row> -->
</template>
