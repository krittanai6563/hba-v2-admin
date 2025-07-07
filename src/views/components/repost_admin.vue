<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';

interface SummaryData {
    yearly_data: Record<string, Record<string, number>>;
    monthly_data: Record<string, Record<number, Record<string, number>>>;
}

const selectyear = ref<string[]>([]);
const selectMonths = ref<string[]>([]);
const selectyearQuarters = ref<string[]>([]);

const year = ['2568', '2567', '2566', '2565'];
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
const Quarters = ['1', '2', '3', '4'];

const currentYear = new Date().getFullYear() + 543;
const selectedYear = ref(currentYear.toString());
const yearOptions = computed(() => Array.from({ length: 7 }, (_, i) => (currentYear - i).toString()));

const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user';
const summaryData = ref<SummaryData | null>(null);

const chartSeries = ref<any[]>([]);

const categoryOrder = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป', 'รวม'];


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

// ฟังก์ชันนี้จะคืนค่าเป็นอาเรย์ของเดือนตามไตรมาสที่เลือก
const getMonthsInQuarter = (quarter: number) => {
    switch (quarter) {
        case 1:
            return [1, 2, 3]; // ไตรมาสที่ 1: มกราคม, กุมภาพันธ์, มีนาคม
        case 2:
            return [4, 5, 6]; // ไตรมาสที่ 2: เมษายน, พฤษภาคม, มิถุนายน
        case 3:
            return [7, 8, 9]; // ไตรมาสที่ 3: กรกฎาคม, สิงหาคม, กันยายน
        case 4:
            return [10, 11, 12]; // ไตรมาสที่ 4: ตุลาคม, พฤศจิกายน, ธันวาคม
        default:
            return []; // หากไม่มีไตรมาสที่ถูกต้องให้คืนค่าอาเรย์ว่าง
    }
};

const fetchSummary = async (type: 'monthly' | 'quarterly') => {
    if (!selectyear.value || selectyear.value.length === 0) {
        console.error('Please select at least one year.');
        return;
    }

    const data: any = {
        user_id: userId,
        buddhist_year: selectyear.value,
        role: userRole,
        quarters: selectyearQuarters.value.map((quarter: string) => {
            // Convert "ไตรมาสที่ X" to numbers
            const quarterNumber = parseInt(quarter.replace('ไตรมาสที่ ', '').trim());
            return quarterNumber;
        })
    };
    console.log('Selected Quarters:', selectyearQuarters.value);
    console.log('Mapped Quarters:', data.quarters);

    // If monthly data is requested, add month data to the request
    if (type === 'monthly' && selectMonths.value.length > 0) {
        data.months = selectMonths.value.map((month: string) => monthMap[month] || null);
    }

    // If quarterly data is requested, add quarters to the request
    if (type === 'quarterly' && selectyearQuarters.value.length > 0) {
        data.quarters = selectyearQuarters.value; // Send selected quarters
    }

    console.log('Sending data to backend:', data);

    try {
        const res = await fetch('http://localhost/package/backend/repost_admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const textResponse = await res.text();
        console.log('Raw response:', textResponse);

        if (!textResponse) {
            throw new Error('Empty response from backend');
        }

        const jsonData = JSON.parse(textResponse);
        console.log('Parsed JSON Data:', jsonData);
        if (jsonData.error) {
            console.error(jsonData.error);
            return;
        }

        summaryData.value = jsonData;

        // After receiving the data, call the function to update the region chart
        updateChartRegionData(jsonData); // This will update the region chart data
        updateChartMembershipData(jsonData); // Update membership chart data


        if (type === 'monthly') {
            updateChartData(jsonData); // For monthly data
        } else if (type === 'quarterly') {
            updateChartQuarterData(jsonData); // For quarterly data
            updateQuarterlyMonthComparisonData(jsonData); // If you have monthly comparison data
        }
    } catch (err) {
        console.error('❌ Error fetching summary:', err);
    }
};

const updateChartData = (data: SummaryData) => {
    const totalValueData: any[] = [];
    const categories: string[] = [];
    const selectedYears = selectyear.value; // เลือกปีที่เลือก
    const selectedMonths = selectMonths.value; // เลือกเดือนที่เลือก

    // กรณีเลือก 1 ปี หลายเดือน (ปีเดียวหลายเดือน)
    if (selectedYears.length === 1 && selectedMonths.length > 1) {
        const selectedYear = selectedYears[0]; // ใช้ปีเดียวที่เลือก

        selectedMonths.forEach((month) => {
            const monthIndex = monthMap[month]; // แปลงชื่อเดือนเป็นหมายเลข
            categories.push(`${Months[monthIndex - 1]} ${selectedYear}`); // หมวดหมู่แสดงเป็น "เดือน-ปี"

            const monthlyData = categoryOrder.map((category) => {
                const monthData = data.monthly_data[selectedYear]?.[monthIndex];
                return monthData && monthData[category] !== undefined ? monthData[category] : 0;
            });

            totalValueData.push({
                name: `${Months[monthIndex - 1]} ${selectedYear}`, // แสดงเดือนและปี
                type: 'column',
                data: monthlyData
            });
        });
    } else if (selectedYears.length > 1 && selectedMonths.length > 1) {
        // กรณีเลือกหลายปีหลายเดือน
        const firstYear = selectedYears[0]; // ปีแรก
        const secondYear = selectedYears[1]; // ปีที่สอง

        selectedMonths.forEach((month, index) => {
            const monthIndex = monthMap[month]; // แปลงชื่อเดือนเป็นหมายเลข

            // ถ้าเป็นเดือนแรก แสดงปีแรก
            if (index === 0) {
                categories.push(`${Months[monthIndex - 1]} ${firstYear}`);

                const monthlyData1 = categoryOrder.map((category) => {
                    const monthData = data.monthly_data[firstYear]?.[monthIndex];
                    return monthData && monthData[category] !== undefined ? monthData[category] : 0;
                });

                totalValueData.push({
                    name: `${Months[monthIndex - 1]} ${firstYear}`,
                    type: 'column',
                    data: monthlyData1
                });
            }

            // ถ้าเป็นเดือนที่สอง แสดงปีที่สอง
            if (index === 1) {
                categories.push(`${Months[monthIndex - 1]} ${secondYear}`);

                const monthlyData2 = categoryOrder.map((category) => {
                    const monthData = data.monthly_data[secondYear]?.[monthIndex];
                    return monthData && monthData[category] !== undefined ? monthData[category] : 0;
                });

                totalValueData.push({
                    name: `${Months[monthIndex - 1]} ${secondYear}`,
                    type: 'column',
                    data: monthlyData2
                });
            }
        });
    } else if (selectedYears.length === 1) {
        // กรณีเลือกปีเดียว
        categories.push(...categoryOrder); // กำหนดหมวดหมู่บนแกน X เป็น category

        const yearlyData = categoryOrder.map((category) => {
            return data.yearly_data[selectedYears[0]]?.[category] || 0; // หากไม่มีข้อมูลให้ใช้ 0 แทน
        });

        totalValueData.push({
            name: `ปี ${selectedYears[0]}`, // แสดงปีที่เลือก
            type: 'column',
            data: yearlyData
        });
    } else if (selectMonths.value.length === 1 && selectedYears.length === 2) {
        // หากเลือก 2 ปี และ 1 เดือน
        selectMonths.value.forEach((month) => {
            const monthIndex = monthMap[month]; // แปลงชื่อเดือนเป็นหมายเลข
            categories.push(`${Months[monthIndex - 1]} ${selectedYears[0]}`); // แสดง ปี-เดือน สำหรับปีแรก

            const monthlyData1 = categoryOrder.map((category) => {
                const monthData = data.monthly_data[selectedYears[0]]?.[monthIndex];
                return monthData && monthData[category] !== undefined ? monthData[category] : 0;
            });

            totalValueData.push({
                name: `${Months[monthIndex - 1]} ${selectedYears[0]}`, // ปีแรก
                type: 'column',
                data: monthlyData1
            });

            categories.push(`${Months[monthIndex - 1]} ${selectedYears[1]}`); // แสดง ปี-เดือน สำหรับปีที่สอง

            const monthlyData2 = categoryOrder.map((category) => {
                const monthData = data.monthly_data[selectedYears[1]]?.[monthIndex];
                return monthData && monthData[category] !== undefined ? monthData[category] : 0;
            });

            totalValueData.push({
                name: `${Months[monthIndex - 1]} ${selectedYears[1]}`, // ปีที่สอง
                type: 'column',
                data: monthlyData2
            });
        });
    } else if (selectMonths.value.length === 0) {
        // หากไม่ได้เลือกเดือน (แสดงข้อมูลรายปี)
        selectedYears.forEach((year) => {
            const yearlyData = categoryOrder.map((category) => {
                return data.yearly_data[year]?.[category] || 0; // หากไม่มีข้อมูลให้ใช้ 0 แทน
            });

            totalValueData.push({
                name: `ปี ${year}`,
                type: 'column',
                data: yearlyData
            });
        });

        categories.push(...categoryOrder); // กำหนดหมวดหมู่บนแกน X เป็น category
    }

    // อัปเดตข้อมูลในกราฟรายเดือน
    chartSeries.value = totalValueData;
    chartOptions.value.xaxis.categories = categories;
};
// Computed property สำหรับการแสดงหัวข้อกราฟ
const chartSubtitle = computed(() => {
    if (selectMonths.value.length === 1 && selectyear.value.length === 1) {
        const selectedMonth = selectMonths.value[0];
        const selectedYear = selectyear.value[0];
        return `เดือน ${selectedMonth} ปี ${selectedYear}`;
    } else if (selectMonths.value.length > 1 && selectyear.value.length === 1) {
        // แสดงทุกเดือนที่เลือกแทนการใช้ช่วงเดือน
        const months = selectMonths.value.join(' - ');
        const selectedYear = selectyear.value[0];
        return `เดือน ${months} ปี ${selectedYear}`;
    } else if (selectMonths.value.length === 1 && selectyear.value.length > 1) {
        const firstYear = selectyear.value[0];
        const lastYear = selectyear.value[selectyear.value.length - 1];
        const selectedMonth = selectMonths.value[0];
        return `เดือน ${selectedMonth} ปี ${firstYear} - ปี ${lastYear}`;
    } else if (selectMonths.value.length > 1 && selectyear.value.length > 1) {
        // แสดงทุกเดือนที่เลือกแทนการใช้ช่วงเดือน
        const months = selectMonths.value.join(' - ');
        const firstYear = selectyear.value[0];
        const lastYear = selectyear.value[selectyear.value.length - 1];
        return `เดือน ${months} ปี ${firstYear} - ปี ${lastYear}`;
    } else if (selectyear.value.length === 1) {
        // กรณีเลือกปีเดียว
        const selectedYear = selectyear.value[0];
        return `ปี ${selectedYear}`;
    } else if (selectyear.value.length > 1) {
        // กรณีเปรียบเทียบหลายปี
        const firstYear = selectyear.value[0];
        const lastYear = selectyear.value[selectyear.value.length - 1];
        return `ปี ${firstYear} - ปี ${lastYear}`;
    }

    return '';
});
// Watch สำหรับกราฟเดือน/ปี
watch(
    [selectyear, selectMonths],
    () => {
        // ถ้ามีการเลือกเดือน
        if (selectMonths.value.length > 0) {
            fetchSummary('monthly'); // เรียก fetchSummary สำหรับกราฟรายเดือน
        }
    },
    { immediate: true }
);
// Computed property สำหรับการแสดงหัวข้อกราฟไตรมาส
const chartQuarterSubtitle = computed(() => {
    if (selectyear.value.length === 1 && selectyearQuarters.value.length === 1) {
        const selectedQuarter = selectyearQuarters.value[0];
        const selectedYear = selectyear.value[0];
        return `ไตรมาส ${selectedQuarter} ปี ${selectedYear}`;
    } else if (selectyear.value.length === 1 && selectyearQuarters.value.length > 1) {
        const firstQuarter = selectyearQuarters.value[0];
        const lastQuarter = selectyearQuarters.value[selectyearQuarters.value.length - 1];
        const selectedYear = selectyear.value[0];
        return `ไตรมาส ${firstQuarter} - ไตรมาส ${lastQuarter} ปี ${selectedYear}`;
    } else if (selectyear.value.length > 1 && selectyearQuarters.value.length === 1) {
        const firstYear = selectyear.value[0];
        const lastYear = selectyear.value[selectyear.value.length - 1];
        const selectedQuarter = selectyearQuarters.value[0];
        return `ไตรมาส ${selectedQuarter} ปี ${firstYear} - ปี ${lastYear}`;
    } else if (selectyear.value.length > 1 && selectyearQuarters.value.length > 1) {
        const firstYear = selectyear.value[0];
        const lastYear = selectyear.value[selectyear.value.length - 1];
        const firstQuarter = selectyearQuarters.value[0];
        const lastQuarter = selectyearQuarters.value[selectyearQuarters.value.length - 1];
        return `ไตรมาส ${firstQuarter} - ไตรมาส ${lastQuarter} ปี ${firstYear} - ปี ${lastYear}`;
    }
    return '';
});

// Define chart options with dynamic y-axis generation
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
        formatter: (value) => {
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
        categories: categoryOrder, // This ensures the X-axis uses the correct categories
        labels: {
            style: {
                fontSize: '12px',
                colors: '#6c757d'
            }
        },
        tickPlacement: 'on' // Ensure ticks align with the categories
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

const chartQuarterSeries = ref<any[]>([]); // กำหนดให้เป็นอาร์เรย์ว่าง

const updateChartQuarterData = (data: SummaryData) => {
    const totalValueData: any[] = [];
    const categories: string[] = [];
    const selectedYears = selectyear.value; // เลือกปีที่เลือก
    const selectedQuarters = selectyearQuarters.value; // เลือกไตรมาสที่เลือก

    // ถ้าผู้ใช้เลือกหลายปีและหลายไตรมาส
    if (selectedYears.length > 1 && selectedQuarters.length > 1) {
        selectedYears.forEach((year, index) => {
            const quarter = selectedQuarters[index]; // จับคู่ปีที่เลือกกับไตรมาส
            const quarterIndex = parseInt(quarter); // แปลงไตรมาสเป็นหมายเลข (1, 2, 3, 4)
            const quarterData = categoryOrder.map((category) => {
                const dataForQuarter = data.quarterly_data[year]?.[quarterIndex];
                return dataForQuarter && dataForQuarter[category] !== undefined ? dataForQuarter[category] : 0;
            });

            const monthsInQuarter = getMonthsInQuarter(quarterIndex); // หารายชื่อเดือนในไตรมาส
            monthsInQuarter.forEach((monthIndex) => {
                const monthName = Months[monthIndex - 1]; // หาเดือนตามหมายเลข
                categories.push(`${monthName} ไตรมาส ${quarter} ปี ${year}`); // แสดงชื่อเดือนและไตรมาสในหมวดหมู่
            });

            totalValueData.push({
                name: `ไตรมาส ${quarter} ปี ${year}`, // กำหนดชื่อกราฟตามไตรมาสและปี
                type: 'column',
                data: quarterData // ใช้ข้อมูลที่ได้จาก `categoryOrder`
            });
        });
    }
    // ถ้าเลือกเพียงปีเดียวหลายไตรมาส
    else if (selectedYears.length === 1 && selectedQuarters.length > 1) {
        selectedQuarters.forEach((quarter, index) => {
            const quarterIndex = parseInt(quarter); // แปลงไตรมาสเป็นหมายเลข (1, 2, 3, 4)
            const quarterData = categoryOrder.map((category) => {
                const dataForQuarter = data.quarterly_data[selectedYears[0]]?.[quarterIndex];
                return dataForQuarter && dataForQuarter[category] !== undefined ? dataForQuarter[category] : 0;
            });

            const monthsInQuarter = getMonthsInQuarter(quarterIndex); // หารายชื่อเดือนในไตรมาส
            monthsInQuarter.forEach((monthIndex) => {
                const monthName = Months[monthIndex - 1]; // หาเดือนตามหมายเลข
                categories.push(`${monthName} ไตรมาส ${quarter} ปี ${selectedYears[0]}`); // แสดงชื่อเดือนและไตรมาสในหมวดหมู่
            });

            totalValueData.push({
                name: `ไตรมาส ${quarter} ปี ${selectedYears[0]}`, // กำหนดชื่อกราฟตามไตรมาสและปี
                type: 'column',
                data: quarterData // ใช้ข้อมูลที่ได้จาก `categoryOrder`
            });
        });
    }
    // กรณีเลือกปีเดียวและไตรมาสเดียว
    else if (selectedYears.length === 1 && selectedQuarters.length === 1) {
        const quarter = selectedQuarters[0];
        const quarterIndex = parseInt(quarter); // แปลงไตรมาสเป็นหมายเลข (1, 2, 3, 4)
        const quarterData = categoryOrder.map((category) => {
            const dataForQuarter = data.quarterly_data[selectedYears[0]]?.[quarterIndex];
            return dataForQuarter && dataForQuarter[category] !== undefined ? dataForQuarter[category] : 0;
        });

        const monthsInQuarter = getMonthsInQuarter(quarterIndex); // หารายชื่อเดือนในไตรมาส
        monthsInQuarter.forEach((monthIndex) => {
            const monthName = Months[monthIndex - 1]; // หาเดือนตามหมายเลข
            categories.push(`${monthName} ไตรมาส ${quarter} ปี ${selectedYears[0]}`); // แสดงชื่อเดือนและไตรมาสในหมวดหมู่
        });

        totalValueData.push({
            name: `ไตรมาส ${quarter} ปี ${selectedYears[0]}`, // กำหนดชื่อกราฟตามไตรมาสและปี
            type: 'column',
            data: quarterData // ใช้ข้อมูลที่ได้จาก `categoryOrder`
        });
    }
    // ถ้าเลือกหลายปีแต่เลือกแค่ 1 ไตรมาส
    else if (selectedYears.length > 1 && selectedQuarters.length === 1) {
        const quarter = selectedQuarters[0]; // เลือกไตรมาสเดียว
        const quarterIndex = parseInt(quarter); // แปลงไตรมาสเป็นหมายเลข (1, 2, 3, 4)

        selectedYears.forEach((year) => {
            const quarterData = categoryOrder.map((category) => {
                const dataForQuarter = data.quarterly_data[year]?.[quarterIndex];
                return dataForQuarter && dataForQuarter[category] !== undefined ? dataForQuarter[category] : 0;
            });

            const monthsInQuarter = getMonthsInQuarter(quarterIndex); // หารายชื่อเดือนในไตรมาส
            monthsInQuarter.forEach((monthIndex) => {
                const monthName = Months[monthIndex - 1]; // หาเดือนตามหมายเลข
                categories.push(`${monthName} ไตรมาส ${quarter} ปี ${year}`); // แสดงชื่อเดือนและไตรมาสในหมวดหมู่
            });

            totalValueData.push({
                name: `ไตรมาส ${quarter} ปี ${year}`, // กำหนดชื่อกราฟตามไตรมาสและปี
                type: 'column',
                data: quarterData // ใช้ข้อมูลที่ได้จาก `categoryOrder`
            });
        });
    }

    // อัปเดตข้อมูลในกราฟไตรมาส
    chartQuarterSeries.value = totalValueData;
    chartQuarterOptions.value.xaxis.categories = categories; // อัปเดตหมวดหมู่ของแกน X สำหรับไตรมาส
};

watch(
    [selectyear, selectyearQuarters],
    () => {
        // ถ้ามีการเลือกไตรมาส
        if (selectyearQuarters.value.length > 0) {
            fetchSummary('quarterly'); // เรียก fetchSummary สำหรับกราฟไตรมาส
        }
    },
    { immediate: true }
);

onMounted(fetchSummary);

const chartQuarterOptions = ref({
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
        formatter: (value) => {
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
        categories: categoryOrder, // This ensures the X-axis uses the correct categories
        labels: {
            style: {
                fontSize: '12px',
                colors: '#6c757d'
            }
        },
        tickPlacement: 'on' // Ensure ticks align with the categories
    },
    yaxis: {
        show: false // Hide the Y-axis
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
// Chart series for the new month comparison chart
const chartMonthComparisonSeries = ref<any[]>([]);
const categories = ref<string[]>([]); 



const updateQuarterlyMonthComparisonData = (data: SummaryData) => {
    const selectedYears = selectyear.value;
    const selectedQuarters = selectyearQuarters.value.map((q) => parseInt(q));
    
    // finalChartSeries จะมีโครงสร้าง: [{ name: "Series A", data: [val1, val2, val3] }, ...]
    // โดยที่ val1 คือค่าสำหรับ Category แรก, val2 สำหรับ Category ที่สอง, ฯลฯ
    const finalChartSeries: any[] = [];
    
    let updatedCategoriesForXAxis: string[] = []; 

    // chartDataBySeriesName:
    // {
    //   "เมษายน (Q2) ปี 2568": { "0": 100, "1": null, "2": null }, // เก็บข้อมูลของ Series นี้ตาม positional index
    //   "พฤษภาคม (Q2) ปี 2568": { "0": null, "1": 200, "2": null },
    //   ...
    // }
    // เราจะเปลี่ยนมันให้เก็บเป็น Array ตรงๆ ที่มีขนาดเท่ากับ Categories
    const chartDataBySeriesName: Record<string, (number | null)[]> = {};

    if (selectedYears.length === 0 || selectedQuarters.length < 2) {
        chartMonthComparisonSeries.value = [];
        categories.value = [];
        return;
    }

    selectedQuarters.sort((a, b) => a - b);
    selectedYears.sort((a, b) => a - b); 

    const categoryDetailsByPositionalIndex: Record<number, { monthName: string; quarterNum: number; year: number; monthIndex: number }[]> = {};
    const allUniquePositionalIndicesWithData: Set<number> = new Set(); 

    // Step 1: Populate chartDataBySeriesName and prepare intermediate structure for categories
    selectedYears.forEach((year) => {
        selectedQuarters.forEach((quarterNum) => {
            const monthsInThisQuarter = getMonthsInQuarter(quarterNum); 
            monthsInThisQuarter.forEach((monthIndex, positionalIndex) => { 
                const monthName = Months[monthIndex - 1];
                const seriesNameForThisBar = `${monthName} (Q${quarterNum}) ปี ${year}`;

                // สร้างโครงสร้างสำหรับ series นี้ใน chartDataBySeriesName ถ้ายังไม่มี
                if (!chartDataBySeriesName[seriesNameForThisBar]) {
                    chartDataBySeriesName[seriesNameForThisBar] = []; 
                }
                
                const rawValue = data.quarterly_month_comparison?.[year]?.[quarterNum]?.[monthIndex]?.['รวม'];
                let value: number | null = null;
                if (typeof rawValue === 'number' && !isNaN(rawValue)) {
                    value = rawValue;
                }
                
                // เก็บค่าตาม positionalIndex
                // ไม่ได้ใช้ .length = positionalIndex + 1 แล้ว
                // เราจะเพิ่มค่าลงไปตรงๆ ในตำแหน่งที่สอดคล้องกับ positionalIndex
                // โดยที่ตอนนี้ chartDataBySeriesName[seriesNameForThisBar] จะมีช่องว่าง
                // ซึ่งเราจะจัดการภายหลังเมื่อรู้ขนาดของ categories ทั้งหมด
                if (!categoryDetailsByPositionalIndex[positionalIndex]) {
                    categoryDetailsByPositionalIndex[positionalIndex] = [];
                }
                categoryDetailsByPositionalIndex[positionalIndex].push({ monthName, quarterNum, year, monthIndex });
                allUniquePositionalIndicesWithData.add(positionalIndex); 

                // เพื่อเตรียมข้อมูลสำหรับแต่ละ Series ให้ถูกต้องตาม Category
                // เราจะเก็บข้อมูลแยกตาม positionalIndex ในตอนนี้
                // และเติม null สำหรับช่องว่างในภายหลัง
                chartDataBySeriesName[seriesNameForThisBar][positionalIndex] = value;
            });
        });
    });

    // Step 2: Generate X-axis categories from the collected details
    const sortedExistingPositionalIndices = Array.from(allUniquePositionalIndicesWithData).sort((a, b) => a - b);

    updatedCategoriesForXAxis = sortedExistingPositionalIndices.map(posIdx => {
        const labelsForThisPos = categoryDetailsByPositionalIndex[posIdx]
            .sort((a, b) => { 
                if (a.quarterNum !== b.quarterNum) {
                    return a.quarterNum - b.quarterNum;
                }
                return a.monthIndex - b.monthIndex;
            })
            .map(detail => `${detail.monthName} (Q${detail.quarterNum})`);
        
        return labelsForThisPos.join(' / ');
    });

    categories.value = updatedCategoriesForXAxis;

    // Step 3: Convert chartDataBySeriesName into finalChartSeries array.
    const uniqueSeriesNamesArray = Array.from(Object.keys(chartDataBySeriesName)); 
    
    // การจัดเรียง Series: จัดเรียงชื่อ Series ตามปี, ไตรมาส, เดือน
    const sortedSeriesKeys = uniqueSeriesNamesArray.sort((keyA, keyB) => {
        const parseKey = (key: string) => {
            const parts = key.split(' ');
            const year = parseInt(parts[parts.length - 1]);
            const monthName = parts[0];
            const quarterNum = parseInt(parts[1].match(/\(Q(\d+)\)/)?.[1] || '0');
            const monthIndex = Months.indexOf(monthName) + 1;
            return { year, monthIndex, quarterNum };
        };

        const detailA = parseKey(keyA);
        const detailB = parseKey(keyB);

        if (detailA.year !== detailB.year) {
            return detailA.year - detailB.year;
        }
        if (detailA.quarterNum !== detailB.quarterNum) {
            return detailA.quarterNum - detailB.quarterNum;
        }
        return detailA.monthIndex - detailB.monthIndex;
    });

    // *** ส่วนสำคัญที่สุดในการแก้ไขปัญหาช่องว่าง ***
    // เราจะสร้าง data array ที่มีขนาดเท่ากับ updatedCategoriesForXAxis
    // และใส่ค่า null ในตำแหน่งที่ไม่มีข้อมูลสำหรับ Series นั้นๆ
    // สิ่งนี้จะทำให้ ApexCharts จัดสรรพื้นที่ได้ถูกต้อง
    sortedSeriesKeys.forEach((key) => {
        const dataForThisSeries: (number | null)[] = [];
        // วนลูปตาม positionalIndex ที่มีข้อมูลจริงในแกน X เท่านั้น เพื่อสร้าง Array ของข้อมูล
        sortedExistingPositionalIndices.forEach(posIdx => {
            // ดึงค่าจาก chartDataBySeriesName ที่เราเก็บไว้
            // ถ้ามีค่า ก็ใช้ค่านั้น ถ้าไม่มี (คือ undefined) ให้ใช้ null
            dataForThisSeries.push(chartDataBySeriesName[key][posIdx] !== undefined ? chartDataBySeriesName[key][posIdx] : null);
        });

        finalChartSeries.push({
            name: key,
            type: 'column',
            data: dataForThisSeries
        });
    });

    chartMonthComparisonSeries.value = finalChartSeries;

    console.log('Final Categories (X-axis labels):', updatedCategoriesForXAxis);
    console.log('Final Series Data (for plotting):', finalChartSeries);
};

// Subtitle for the month comparison chart (remains unchanged)
const chartMonthComparisonSubtitle = computed(() => {
    if (selectyear.value.length === 1 && selectyearQuarters.value.length === 1) {
        const selectedQuarter = selectyearQuarters.value[0];
        const selectedYear = selectyear.value[0];
        return `เปรียบเทียบเดือนในไตรมาส ${selectedQuarter} ปี ${selectedYear}`;
    } else if (selectyear.value.length > 1 && selectyearQuarters.value.length === 1) {
        const firstYear = selectyear.value[0];
        const lastYear = selectyear.value[selectyear.value.length - 1];
        const selectedQuarter = selectyearQuarters.value[0];
        return `เปรียบเทียบเดือนในไตรมาส ${selectedQuarter} ปี ${firstYear} - ปี ${lastYear}`;
    } else if (selectyear.value.length === 1 && selectyearQuarters.value.length > 1) {
        const selectedYear = selectyear.value[0];
        const firstQuarter = selectyearQuarters.value[0];
        const lastQuarter = selectyearQuarters.value[selectyearQuarters.value.length - 1];
        return `เปรียบเทียบเดือนในไตรมาส ${firstQuarter} - ไตรมาส ${lastQuarter} ปี ${selectedYear}`;
    } else if (selectyear.value.length > 1 && selectyearQuarters.value.length > 1) {
        const firstYear = selectyear.value[0];
        const lastYear = selectyear.value[selectyear.value.length - 1];
        const firstQuarter = selectyearQuarters.value[0];
        const lastQuarter = selectyearQuarters.value[selectyearQuarters.value.length - 1];
        return `เปรียบเทียบเดือนในไตรมาส ${firstQuarter} - ไตรมาส ${lastQuarter} ปี ${firstYear} - ปี ${lastYear}`;
    }
    return '';
});

const chartMonthComparisonOptions = ref({
    chart: {
        height: 350,
        type: 'column',
        stacked: true, 
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
        toolbar: {
            show: true,
            tools: {
                download: true
            },
            // ลองปรับตำแหน่ง Toolbar เพื่อไม่ให้กินพื้นที่กราฟ
            // offsetY: -20, 
            // offsetX: 0
        },
        zoom: {
            enabled: true,
            type: 'xy'
        },
        // *** ตั้ง padding เป็น 0 ทั้งหมดเพื่อกำจัดพื้นที่ว่างที่ไม่จำเป็นใน chart canvas ***
        padding: {
            left: 0, 
            right: 0, 
            top: 0,
            bottom: 0
        }
    },
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '50%', // *** บังคับให้แท่งกราฟกว้างที่สุดเท่าที่พื้นที่ Category จะอำนวย ***
            // barHeight: '100%', // ปกติไม่ใช้กับ Column chart แต่อาจลองได้
            dataLabels: {
                position: 'top',
                offsetY: 0
            }
        }
    },
    dataLabels: {
        enabled: true,
        position: 'top',
        offsetY: -13, 
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
        width: 0, 
        curve: 'smooth'
    },
    grid: {
        show: true,
        strokeDashArray: 4,
        borderColor: 'rgba(0, 0, 0, 0.1)',
        // *** ตั้ง padding ของ grid เป็น 0 ทั้งหมด ***
        padding: {
            top: 0,
            right: 0,
            bottom: 0,
            left: 0
        }
    },
    xaxis: {
        categories: categories.value, 
        labels: {
            style: {
                fontSize: '12px',
                colors: '#6c757d'
            },
            formatter: function (val: string | null | undefined) {
                if (typeof val !== 'string' || val === null || val === undefined || val === '') {
                    return '';
                }
                return val.replace(/\(Q(\d+)\)/g, '(ไตรมาสที่ $1)');
            },
            // trim: true, 
            // rotate: -45, 
            // *** ลองตั้ง offsetX/offsetY เป็น 0 เพื่อให้แน่ใจว่า Label ไม่เคลื่อนที่ออกนอกพื้นที่ ***
            offsetX: 0, 
            offsetY: 0
        },
        // *** tickPlacement เป็น 'on' ถูกต้องแล้วสำหรับ Category ที่มีหลาย Bar ในแต่ละ Tick ***
        tickPlacement: 'on', 
        axisBorder: {
            show: true,
            color: '#e0e0e0',
            height: 0,
            // *** ตั้ง width ให้เป็น 100% เพื่อให้เส้นขอบแกนครอบคลุมเต็มพื้นที่ X-axis ***
            width: '100%', 
            offsetX: 0,
            offsetY: 0
        },
        axisTicks: {
            // *** ตั้ง show เป็น false เพื่อกำจัดขีดแบ่งถ้ามันรบกวนการจัดวาง ***
            show: false, 
            borderType: 'solid',
            color: '#e0e0e0',
            height: 6,
            offsetX: 0,
            offsetY: 0
        }
    },
    tooltip: {
        enabled: true,
        fixed: {
            enabled: false,
            position: 'topLeft',
            offsetY: 0,
            offsetX: 0
        },
        x: {
            formatter: function (val: string) {
                if (typeof val === 'string') {
                    return val.replace(/\(Q(\d+)\)/g, '(ไตรมาสที่ $1)');
                }
                return val;
            }
        },
        y: {
            formatter: function (val: number | null | undefined) {
                if (val === null || val === undefined) {
                    return undefined; 
                }
                return val >= 1000 ? val.toLocaleString('th-TH') : val.toString();
            }
        },
        marker: {
            show: true
        }
    },
    legend: {
        horizontalAlign: 'center',
        offsetX: 0
    }
});





const fixedRegionOrder = [
    'กรุงเทพปริมณฑล',
    'ภาคกลาง',
    'ภาคเหนือ',
    'ภาคตะวันออกเฉียงเหนือ',
    'ภาคตะวันออก',
    'ภาคตะวันตก',
    'ภาคใต้',
];

const updateChartRegionData = (data: SummaryData) => {
    if (!data || !data.region_data) {
        console.error('No region data available');
        chartRegionSeries.value = [];
        chartRegionOptions.value.xaxis.categories = fixedRegionOrder; // Set to fixed order even if no data
        return;
    }

    const selectedYears = selectyear.value;
    const selectedQuarters = selectyearQuarters.value.map((q) => parseInt(q));

    // Separate data for Q1, Q2, Q3 and Q4
    const q1RegionSeriesData: number[] = [];
    const q2RegionSeriesData: number[] = [];
    const q3RegionSeriesData: number[] = [];
    const q4RegionSeriesData: number[] = [];
    const chartCategories: string[] = []; // This will always be fixedRegionOrder

    // Iterate through the fixed order of regions
    fixedRegionOrder.forEach(region => {
        let q1TotalSum = 0; // Sum for Q1
        let q2TotalSum = 0; // Sum for Q2
        let q3TotalSum = 0; // Sum for Q3
        let q4TotalSum = 0; // Sum for Q4

        // Add the region name to categories (ensures fixed order)
        chartCategories.push(region);

        // Check if the region exists in the data before trying to access its years
        if (data.region_data[region]) {
            // Handle the cases for selected years and selected quarters
            if (selectedYears.length === 1) {
                // Case: One Year, Multiple Quarters (1 ปี หลายไตรมาส)
                if (selectedQuarters.length > 1) {
                    selectedQuarters.forEach(quarterNum => {
                        const quarterValue = data.region_data[region][selectedYears[0]]?.[quarterNum];
                        if (quarterValue !== undefined && typeof quarterValue === 'number' && !isNaN(quarterValue)) {
                            // Check and sum the value for each quarter
                            if (quarterNum === 2) q2TotalSum += quarterValue;
                            else if (quarterNum === 3) q3TotalSum += quarterValue;
                            else if (quarterNum === 4) q4TotalSum += quarterValue;
                            else q1TotalSum += quarterValue;
                        }
                    });
                } else {
                    // Case: One Year, One Quarter (1 ปี 1 ไตรมาส)
                    selectedQuarters.forEach(quarterNum => {
                        const quarterValue = data.region_data[region][selectedYears[0]]?.[quarterNum];
                        if (quarterValue !== undefined && typeof quarterValue === 'number' && !isNaN(quarterValue)) {
                            // Check and sum the value for the selected quarter
                            if (quarterNum === 2) q2TotalSum += quarterValue;
                            else if (quarterNum === 3) q3TotalSum += quarterValue;
                            else if (quarterNum === 4) q4TotalSum += quarterValue;
                            else q1TotalSum += quarterValue;
                        }
                    });
                }
            } else if (selectedYears.length > 1) {
                // Case: Multiple Years, One Quarter (หลายปี 1 ไตรมาส)
                if (selectedQuarters.length === 1) {
                    selectedYears.forEach(year => {
                        const quarterValue = data.region_data[region][year]?.[selectedQuarters[0]];
                        if (quarterValue !== undefined && typeof quarterValue === 'number' && !isNaN(quarterValue)) {
                            // Sum the values for the specific quarter across all years
                            if (selectedQuarters[0] === 2) q2TotalSum += quarterValue;
                            else if (selectedQuarters[0] === 3) q3TotalSum += quarterValue;
                            else if (selectedQuarters[0] === 4) q4TotalSum += quarterValue;
                            else q1TotalSum += quarterValue;
                        }
                    });
                } else {
                    // Case: Multiple Years, Multiple Quarters (หลายปี หลายไตรมาส)
                    selectedYears.forEach((year, idx) => {
                        const quarterNum = selectedQuarters[idx % selectedQuarters.length]; // Mapping quarter for each year
                        const quarterValue = data.region_data[region][year]?.[quarterNum];
                        if (quarterValue !== undefined && typeof quarterValue === 'number' && !isNaN(quarterValue)) {
                            // Sum the values based on the quarter for each year
                            if (quarterNum === 2) q2TotalSum += quarterValue;
                            else if (quarterNum === 3) q3TotalSum += quarterValue;
                            else if (quarterNum === 4) q4TotalSum += quarterValue;
                            else q1TotalSum += quarterValue;
                        }
                    });
                }
            }
        }

        // Push the results for each quarter (Q1, Q2, Q3, Q4)
        q1RegionSeriesData.push(q1TotalSum);
        q2RegionSeriesData.push(q2TotalSum);
        q3RegionSeriesData.push(q3TotalSum);
        q4RegionSeriesData.push(q4TotalSum);
    });

    // Create different series based on the selected quarters
    const seriesData: any[] = [];

    if (selectedQuarters.includes(1)) {
        seriesData.push({
            name: 'Q1 (ไตรมาสที่ 1)',
            type: 'column',
            data: q1RegionSeriesData
        });
    }

    if (selectedQuarters.includes(2)) {
        seriesData.push({
            name: 'Q2 (ไตรมาสที่ 2)',
            type: 'column',
            data: q2RegionSeriesData
        });
    }

    if (selectedQuarters.includes(3)) {
        seriesData.push({
            name: 'Q3 (ไตรมาสที่ 3)',
            type: 'column',
            data: q3RegionSeriesData
        });
    }

    if (selectedQuarters.includes(4)) {
        seriesData.push({
            name: 'Q4 (ไตรมาสที่ 4)',
            type: 'column',
            data: q4RegionSeriesData
        });
    }

    // Update chart series
    chartRegionSeries.value = seriesData;

    // Assign the fixed categories for the x-axis
    chartRegionOptions.value.xaxis.categories = chartCategories;

    console.log('Region Chart Categories (Fixed Order):', chartCategories);
    console.log('Region Chart Series Data (for plotting):', chartRegionSeries.value);
};


const chartRegionSubtitle = computed(() => {
    if (selectyear.value.length === 1 && selectyearQuarters.value.length === 1) {
        const selectedQuarter = selectyearQuarters.value[0];
        const selectedYear = selectyear.value[0];
        return `ไตรมาส ${selectedQuarter} ปี ${selectedYear}`;
    } else if (selectyear.value.length > 1 && selectyearQuarters.value.length === 1) {
        const firstYear = selectyear.value[0];
        const lastYear = selectyear.value[selectyear.value.length - 1];
        const selectedQuarter = selectyearQuarters.value[0];
        return `ไตรมาส ${selectedQuarter} ปี ${firstYear} - ปี ${lastYear}`;
    } else if (selectyear.value.length === 1 && selectyearQuarters.value.length > 1) {
        const selectedYear = selectyear.value[0];
        const firstQuarter = selectyearQuarters.value[0];
        const lastQuarter = selectyearQuarters.value[selectyearQuarters.value.length - 1];
        return `ไตรมาส ${firstQuarter} - ไตรมาส ${lastQuarter} ปี ${selectedYear}`;
    } else if (selectyear.value.length > 1 && selectyearQuarters.value.length > 1) {
        const firstYear = selectyear.value[0];
        const lastYear = selectyear.value[selectyear.value.length - 1];
        const firstQuarter = selectyearQuarters.value[0];
        const lastQuarter = selectyearQuarters.value[selectyearQuarters.value.length - 1];
        return `ไตรมาส ${firstQuarter} - ไตรมาส ${lastQuarter} ปี ${firstYear} - ปี ${lastYear}`;
    }
    return '';
});
 

const chartRegionSeries = ref<any[]>([]); // Empty array for region chart data

const chartRegionOptions = ref({
    chart: {
        height: 350,
        type: 'bar',
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
            columnWidth: '30%',
            dataLabels: {
                position: 'top',
                offsetY: 0
            }
        }
    },
    dataLabels: {
        enabled: true,
        position: 'top',
        offsetY: -13,
        style: {
            fontSize: '10px'
        },
        formatter: (value) => {
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
        categories: fixedRegionOrder, // <-- Change this line
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


interface MonthlyMembershipData {
    filled_count: number;
    not_filled_count: number;
    total_users_for_month: number;
}

interface YearlyMembershipData {
    [month: number]: MonthlyMembershipData;
}

interface MembershipData {
    monthly_membership_data: {
        [year: number]: YearlyMembershipData;
    };
    
}

interface SummaryData {
    membership_data: MembershipData;
   
}


const chartMembershipSeries = ref<any[]>([]);

const updateChartMembershipData = (data: SummaryData) => {
    const membershipData = data.membership_data.monthly_membership_data;
    const newChartSeries: any[] = [];

    // 1. กำหนดเดือนในไตรมาสแรกที่ถูกเลือกเป็นฐาน
    let baseMonths: number[] = [];
    if (selectyearQuarters.value.length > 0) {
        baseMonths = getMonthsInQuarter(parseInt(selectyearQuarters.value[0].toString()));
        baseMonths.sort((a, b) => a - b); // เรียงเดือน
    }

    const aggregatedData: {
        [baseMonthIndex: number]: {
            notFilled: number;
            totalUsers: number;
            filledByYear: { [year: string]: number };
        }
    } = {};

    baseMonths.forEach((_, index) => {
        aggregatedData[index] = {
            notFilled: 0,
            totalUsers: 0,
            filledByYear: {}
        };
        selectyear.value.forEach(year => {
            aggregatedData[index].filledByYear[year.toString()] = 0;
        });
    });

    const categories: string[] = [];

    baseMonths.forEach((baseMonth, baseMonthIndex) => {
        let categoryLabel = Months[baseMonth - 1]; // เริ่มต้นด้วยชื่อเดือนฐาน

        // วนลูปผ่านไตรมาสที่เลือกทั้งหมด
        selectyearQuarters.value.forEach((quarter, quarterIndex) => {
            const currentQuarterMonths = getMonthsInQuarter(parseInt(quarter.toString()));

            // ตรวจสอบให้แน่ใจว่าเดือนในไตรมาสปัจจุบันมี index ที่ตรงกัน
            if (currentQuarterMonths[baseMonthIndex] !== undefined) {
                const actualMonth = currentQuarterMonths[baseMonthIndex];

                // ถ้าเป็นไตรมาสแรก ไม่ต้องเพิ่มชื่อเดือนซ้ำใน label
                if (quarterIndex > 0) {
                    categoryLabel += `/${Months[actualMonth - 1]}`;
                }

                // รวมข้อมูลสำหรับแต่ละเดือนที่เทียบกัน
                selectyear.value.forEach(year => {
                    const monthData = membershipData[year]?.[actualMonth];
                    if (monthData) {
                        aggregatedData[baseMonthIndex].notFilled += monthData.not_filled_count;
                        aggregatedData[baseMonthIndex].totalUsers += monthData.total_users_for_month;
                        aggregatedData[baseMonthIndex].filledByYear[year.toString()] += monthData.filled_count;
                    }
                });
            }
        });
        categories.push(categoryLabel);
    });

    // 4. เตรียม data สำหรับแต่ละ series จาก aggregatedData
    const finalNotFilledData: number[] = [];
    const finalTotalUsersData: number[] = [];
    const finalFilledData: { [key: string]: number[] } = {};
    selectyear.value.forEach(year => {
        finalFilledData[year.toString()] = [];
    });

    baseMonths.forEach((_, index) => {
        finalNotFilledData.push(aggregatedData[index].notFilled);
        finalTotalUsersData.push(aggregatedData[index].totalUsers);
        selectyear.value.forEach(year => {
            finalFilledData[year.toString()].push(aggregatedData[index].filledByYear[year.toString()]);
        });
    });


    console.log("New Categories:", categories);
    console.log("Final Not Filled Data:", finalNotFilledData);
    console.log("Final Total Users Data:", finalTotalUsersData);
    selectyear.value.forEach(year => {
        console.log(`Final Filled Data (${year}):`, finalFilledData[year.toString()]);
    });

    // 5. สร้าง newChartSeries เหมือนเดิม โดยใช้ final data ที่รวมแล้ว

    const baseOffsetY = -13;
    const offsetIncrement = -12;

    newChartSeries.push({
        name: 'ยังไม่กรอกข้อมูล',
        type: 'column',
        data: finalNotFilledData,
        color: '#3498db',
        dataLabels: {
            enabled: true,
            position: 'top',
            offsetY: baseOffsetY,
            style: {
                fontSize: '10px'
            },
            formatter: (value: number) => {
                return value >= 1000 ? value.toLocaleString('th-TH') : value.toString();
            }
        }
    });

    newChartSeries.push({
        name: 'ผู้ใช้ทั้งหมด',
        type: 'column',
        data: finalTotalUsersData,
        color: '#2ecc71',
        dataLabels: {
            enabled: true,
            position: 'top',
            offsetY: baseOffsetY + offsetIncrement,
            style: {
                fontSize: '10px'
            },
            formatter: (value: number) => {
                return value >= 1000 ? value.toLocaleString('th-TH') : value.toString();
            }
        }
    });

    const availableColors = ['#f39c12', '#9b59b6', '#e74c3c', '#34495e'];
    selectyear.value.forEach((year, index) => {
        const currentOffsetY = baseOffsetY + (offsetIncrement * (index + 2));
        newChartSeries.push({
            name: `กรอกข้อมูลแล้ว (${year})`,
            type: 'column',
            data: finalFilledData[year.toString()],
            color: availableColors[index % availableColors.length],
            dataLabels: {
                enabled: true,
                position: 'top',
                offsetY: currentOffsetY,
                style: {
                    fontSize: '10px'
                },
                formatter: (value: number) => {
                    return value >= 1000 ? value.toLocaleString('th-TH') : value.toString();
                }
            }
        });
    });

    chartMembershipSeries.value = newChartSeries;

    chartMembershipOptions.value = {
        ...chartMembershipOptions.value,
        xaxis: {
            ...chartMembershipOptions.value.xaxis,
            type: 'category',
            categories: categories,
            labels: {
                ...chartMembershipOptions.value.xaxis.labels,
                formatter: function (val: string) {
                    return val;
                },
                rotate: 0,
                rotateAlways: false,
                hideOverlappingLabels: false
            },
            tickPlacement: 'between',
            min: 0,
            max: categories.length > 0 ? categories.length - 1 : 0
        },
      
        plotOptions: {
            bar: {
                ...chartMembershipOptions.value.plotOptions.bar,
                columnWidth: '70%',
            }
        },
        dataLabels: {
            enabled: true
        }
    };
};
// const chartMembershipSubtitle = computed(() => {
//     // Adjusted to show all selected years and quarters
//     const years = selectyear.value.length > 0 ? selectyear.value.join(', ') : 'ทุกปี';
//     const quarters = selectyearQuarters.value.length > 0 ? selectyearQuarters.value.join(', ') : 'ทุกไตรมาส';
//     // return `ข้อมูลสมาชิกที่กรอกข้อมูลและผู้ใช้ทั้งหมดสำหรับไตรมาส ${quarters} ปี ${years}`;
// });

const chartMembershipSubtitle = computed(() => {
    if (selectyear.value.length === 1 && selectyearQuarters.value.length === 1) {
        const selectedQuarter = selectyearQuarters.value[0];
        const selectedYear = selectyear.value[0];
        return `เปรียบเทียบยอดเซ็นสัญญาแยกตามภูมิภาค ไตรมาส ${selectedQuarter} ปี ${selectedYear}`;
    } else if (selectyear.value.length > 1 && selectyearQuarters.value.length === 1) {
        const firstYear = selectyear.value[0];
        const lastYear = selectyear.value[selectyear.value.length - 1];
        const selectedQuarter = selectyearQuarters.value[0];
        return `เปรียบเทียบยอดเซ็นสัญญาแยกตามภูมิภาค ไตรมาส ${selectedQuarter} ปี ${firstYear} - ปี ${lastYear}`;
    } else if (selectyear.value.length === 1 && selectyearQuarters.value.length > 1) {
        const selectedYear = selectyear.value[0];
        const firstQuarter = selectyearQuarters.value[0];
        const lastQuarter = selectyearQuarters.value[selectyearQuarters.value.length - 1];
        return `เปรียบเทียบยอดเซ็นสัญญาแยกตามภูมิภาค ไตรมาส ${firstQuarter} - ไตรมาส ${lastQuarter} ปี ${selectedYear}`;
    } else if (selectyear.value.length > 1 && selectyearQuarters.value.length > 1) {
        const firstYear = selectyear.value[0];
        const lastYear = selectyear.value[selectyear.value.length - 1];
        const firstQuarter = selectyearQuarters.value[0];
        const lastQuarter = selectyearQuarters.value[selectyearQuarters.value.length - 1];
        return `เปรียบเทียบยอดเซ็นสัญญาแยกตามภูมิภาค ไตรมาส ${firstQuarter} - ไตรมาส ${lastQuarter} ปี ${firstYear} - ปี ${lastYear}`;
    }
    return '';
});



const chartMembershipOptions = ref({
    chart: {
        height: 350,
        type: 'bar',
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
            columnWidth: '30%',
            dataLabels: {
                position: 'top',
                offsetY: 0
            }
        }
    },
    dataLabels: {
        enabled: true,
        position: 'top',
        offsetY: -13,
        style: {
            fontSize: '10px'
        },
        formatter: (value) => {
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
        categories: [],
        labels: {
            style: {
                fontSize: '12px',
                colors: '#6c757d'
            },
            formatter: function (val) {
                return val;
            },
            rotate: 0, 
            rotateAlways: true,
            hideOverlappingLabels: false
        },
        tickPlacement: 'between' // เปลี่ยนจาก 'on' เป็น 'between'
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


</script>

<template>
    <v-app>
        <v-container>
            <!-- Report title -->
            <v-row>
                <v-col cols="12">
                    <div class="mt-3 mb-6">
                        <div class="d-flex justify-space-between">
                            <div class="d-flex py-0 align-center">
                                <div>
                                    <h3 class="text-h5 card-title">รายงานเปรียบเทียบยอดเซ็นสัญญา</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </v-col>
            </v-row>

            <!-- Filter form for comparison -->
            <v-row>
                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">เลือกข้อมูลที่ใช้สำหรับเปรียบเทียบยอดเซ็นสัญญา</h3>
                            <h5 class="card-subtitle">สามารถเลือกข้อมูลสำหรับการค้นหา</h5>
                            <br />

                            <v-row>
                                <!-- Select Year -->
                                <v-col cols="12" md="4">
                                    <v-container fluid>
                                        <v-row>
                                            <v-combobox v-model="selectyear" :items="year" label="เลือกปี" chips multiple></v-combobox>
                                        </v-row>
                                    </v-container>
                                </v-col>

                                <!-- Select Month -->
                                <v-col cols="12" md="4">
                                    <v-container fluid>
                                        <v-row>
                                            <v-combobox
                                                v-model="selectMonths"
                                                :items="Months"
                                                label="เลือกเดือน"
                                                chips
                                                multiple
                                            ></v-combobox>
                                        </v-row>
                                    </v-container>
                                </v-col>

                                <!-- Select Quarter -->
                                <v-col cols="12" md="4">
                                    <v-container fluid>
                                        <v-row>
                                            <v-combobox
                                                v-model="selectyearQuarters"
                                                :items="Quarters"
                                                label="ไตรมาส"
                                                chips
                                                multiple
                                            ></v-combobox>
                                        </v-row>
                                    </v-container>
                                </v-col>
                            </v-row>
                        </v-card-text>
                    </v-card>
                </v-col>

                <!-- ApexChart Display for Monthly -->
                <!-- ApexChart Display for Monthly -->
                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">กราฟเปรียบเทียบยอดเซ็นสัญญา แยกตามมูลค่าบ้าน (รายเดือน)</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>
                            <apexchart type="line" :options="chartOptions" :series="chartSeries" height="350" />
                        </v-card-text>
                    </v-card>
                </v-col>

                <!-- ApexChart Display for Quarter -->
                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">กราฟเปรียบเทียบยอดเซ็นสัญญา แยกตามมูลค่าบ้าน (ไตรมาส)</h3>
                            <h5 class="card-subtitle">{{ chartQuarterSubtitle }}</h5>
                            <apexchart type="line" :options="chartQuarterOptions" :series="chartQuarterSeries" height="350" />
                        </v-card-text>
                    </v-card>
                </v-col>

         

                <v-col cols="12">
    <v-card>
        <v-card-text>
            <h3 class="card-title mb-1">กราฟเปรียบเทียบยอดเซ็นสัญญา แยกตามภูมิภาค</h3>
            <h5 class="card-subtitle">{{ chartRegionSubtitle }}</h5>
            <apexchart
                type="line"
                :options="chartRegionOptions"
                :series="chartRegionSeries"
                height="350"
            />
        </v-card-text>
    </v-card>
</v-col>

<!-- ApexChart Display for Membership Data -->
<v-col cols="12">
    <v-card>
        <v-card-text>
            <h3 class="card-title mb-1">กราฟเปรียบเทียบข้อมูลสมาชิกที่กรอกข้อมูล ไตรมาส</h3>
            <h5 class="card-subtitle">{{ chartMembershipSubtitle }}</h5>
            <apexchart
                type="line"
                :options="chartMembershipOptions"
                :series="chartMembershipSeries"
                height="350"
            />
        </v-card-text>
    </v-card>
</v-col>

            </v-row>
        </v-container>
    </v-app>
</template>
