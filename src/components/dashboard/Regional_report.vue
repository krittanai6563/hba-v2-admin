<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';

interface SummaryData {
    yearly_data: Record<string, Record<string, number>>;
    monthly_data: Record<string, Record<number, Record<string, number>>>;
    region_data?: Record<string, any>;
    quarterly_data?: Record<string, Record<number, Record<string, number>>>;
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

const getMonthsInQuarter = (quarter: number) => {
    switch (quarter) {
        case 1:
            return [1, 2, 3];
        case 2:
            return [4, 5, 6];
        case 3:
            return [7, 8, 9];
        case 4:
            return [10, 11, 12];
        default:
            return [];
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
            const quarterNumber = parseInt(quarter.replace('ไตรมาสที่ ', '').trim());
            return quarterNumber;
        })
    };
    console.log('Selected Quarters:', selectyearQuarters.value);
    console.log('Mapped Quarters:', data.quarters);

    if (type === 'monthly' && selectMonths.value.length > 0) {
        data.months = selectMonths.value.map((month: string) => monthMap[month] || null);
    }

    if (type === 'quarterly' && selectyearQuarters.value.length > 0) {
        data.quarters = selectyearQuarters.value;
    }

    console.log('Sending data to backend:', data);

    try {
        const res = await fetch('https://uat.hba-sales.org/backend/repost_admin.php', {
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

        updateChartRegionData(jsonData);
       

        if (type === 'monthly') {
               updateChartQuarterData(jsonData);
        } else if (type === 'quarterly') {
            updateChartQuarterData(jsonData);
         
        }
    } catch (err) {
        console.error('❌ Error fetching summary:', err);
    }
};


const chartQuarterSeries = ref<any[]>([]);

const updateChartQuarterData = (data: SummaryData) => {
    const totalValueData: any[] = [];
    const categories: string[] = [];
    const selectedYears = selectyear.value;
    const selectedQuarters = selectyearQuarters.value;

    if (selectedYears.length > 1 && selectedQuarters.length > 1) {
        selectedYears.forEach((year, index) => {
            const quarter = selectedQuarters[index];
            const quarterIndex = parseInt(quarter);

            const quarterData = categoryOrder.map((category) => {
                const dataForQuarter = data.quarterly_data?.[year]?.[quarterIndex];
                return dataForQuarter && dataForQuarter[category] !== undefined ? dataForQuarter[category] : 0;
            });

            const monthsInQuarter = getMonthsInQuarter(quarterIndex);
            monthsInQuarter.forEach((monthIndex) => {
                const monthName = Months[monthIndex - 1];
                categories.push(`${monthName} ไตรมาส ${quarter} ปี ${year}`);
            });

            totalValueData.push({
                name: `ไตรมาส ${quarter} ปี ${year}`,
                type: 'column',
                data: quarterData
            });
        });
    } else if (selectedYears.length === 1 && selectedQuarters.length > 1) {
        selectedQuarters.forEach((quarter, index) => {
            const quarterIndex = parseInt(quarter);
            const quarterData = categoryOrder.map((category) => {
                const dataForQuarter = data.quarterly_data?.[selectedYears[0]]?.[quarterIndex];
                return dataForQuarter && dataForQuarter[category] !== undefined ? dataForQuarter[category] : 0;
            });

            const monthsInQuarter = getMonthsInQuarter(quarterIndex);
            monthsInQuarter.forEach((monthIndex) => {
                const monthName = Months[monthIndex - 1];
                categories.push(`${monthName} ไตรมาส ${quarter} ปี ${selectedYears[0]}`);
            });

            totalValueData.push({
                name: `ไตรมาส ${quarter} ปี ${selectedYears[0]}`,
                type: 'column',
                data: quarterData
            });
        });
    } else if (selectedYears.length === 1 && selectedQuarters.length === 1) {
        const quarter = selectedQuarters[0];
        const quarterIndex = parseInt(quarter);
        const quarterData = categoryOrder.map((category) => {
            const year = selectedYears[0];
            if (data.quarterly_data && data.quarterly_data[year]) {
                const dataForQuarter = data.quarterly_data[year][quarterIndex];
                return dataForQuarter && dataForQuarter[category] !== undefined ? dataForQuarter[category] : 0;
            }
            return 0;
        });

        const monthsInQuarter = getMonthsInQuarter(quarterIndex);
        monthsInQuarter.forEach((monthIndex) => {
            const monthName = Months[monthIndex - 1];
            categories.push(`${monthName} ไตรมาส ${quarter} ปี ${selectedYears[0]}`);
        });

        totalValueData.push({
            name: `ไตรมาส ${quarter} ปี ${selectedYears[0]}`,
            type: 'column',
            data: quarterData
        });
    } else if (selectedYears.length > 1 && selectedQuarters.length === 1) {
        const quarter = selectedQuarters[0];
        const quarterIndex = parseInt(quarter);

        selectedYears.forEach((year) => {
            const quarterData = categoryOrder.map((category) => {
                if (data.quarterly_data && data.quarterly_data[year]) {
                    const dataForQuarter = data.quarterly_data[year][quarterIndex];
                    return dataForQuarter && dataForQuarter[category] !== undefined ? dataForQuarter[category] : 0;
                }
                return 0;
            });

            const monthsInQuarter = getMonthsInQuarter(quarterIndex);
            monthsInQuarter.forEach((monthIndex) => {
                const monthName = Months[monthIndex - 1];
                categories.push(`${monthName} ไตรมาส ${quarter} ปี ${year}`);
            });

            totalValueData.push({
                name: `ไตรมาส ${quarter} ปี ${year}`,
                type: 'column',
                data: quarterData
            });
        });
    } 

    chartQuarterSeries.value = totalValueData;
    
};

watch(
    [selectyear, selectyearQuarters],
    () => {
        if (selectyearQuarters.value.length > 0) {
            fetchSummary('quarterly');
        }
    },
    { immediate: true }
);

onMounted(fetchSummary);


const fixedRegionOrder = ['กรุงเทพปริมณฑล', 'ภาคกลาง', 'ภาคเหนือ', 'ภาคตะวันออกเฉียงเหนือ', 'ภาคตะวันออก', 'ภาคตะวันตก', 'ภาคใต้'];

const updateChartRegionData = (data: SummaryData) => {
    if (!data?.region_data) {
        console.error('No region data available');
        chartRegionSeries.value = [];
        chartRegionOptions.value.xaxis.categories = fixedRegionOrder;
        return;
    }

    const selectedYears = selectyear.value;
    const selectedQuarters = selectyearQuarters.value.map((q) => parseInt(q));

    const q1RegionSeriesData: number[] = [];
    const q2RegionSeriesData: number[] = [];
    const q3RegionSeriesData: number[] = [];
    const q4RegionSeriesData: number[] = [];
    const chartCategories: string[] = [];

    fixedRegionOrder.forEach((region) => {
        let q1TotalSum = 0;
        let q2TotalSum = 0;
        let q3TotalSum = 0;
        let q4TotalSum = 0;

        chartCategories.push(region);

        const regionData = data.region_data?.[region];

        if (regionData) {
            if (selectedYears.length === 1) {
                if (selectedQuarters.length > 1) {
                    selectedQuarters.forEach((quarterNum) => {
                        const quarterValue = regionData[selectedYears[0]]?.[quarterNum];
                        if (quarterValue !== undefined && typeof quarterValue === 'number' && !isNaN(quarterValue)) {
                            if (quarterNum === 2) q2TotalSum += quarterValue;
                            else if (quarterNum === 3) q3TotalSum += quarterValue;
                            else if (quarterNum === 4) q4TotalSum += quarterValue;
                            else q1TotalSum += quarterValue;
                        }
                    });
                } else {
                    selectedQuarters.forEach((quarterNum) => {
                        const quarterValue = regionData[selectedYears[0]]?.[quarterNum];
                        if (quarterValue !== undefined && typeof quarterValue === 'number' && !isNaN(quarterValue)) {
                            if (quarterNum === 2) q2TotalSum += quarterValue;
                            else if (quarterNum === 3) q3TotalSum += quarterValue;
                            else if (quarterNum === 4) q4TotalSum += quarterValue;
                            else q1TotalSum += quarterValue;
                        }
                    });
                }
            } else if (selectedYears.length > 1) {
                if (selectedQuarters.length === 1) {
                    selectedYears.forEach((year) => {
                        const quarterValue = regionData[year]?.[selectedQuarters[0]];
                        if (quarterValue !== undefined && typeof quarterValue === 'number' && !isNaN(quarterValue)) {
                            if (selectedQuarters[0] === 2) q2TotalSum += quarterValue;
                            else if (selectedQuarters[0] === 3) q3TotalSum += quarterValue;
                            else if (selectedQuarters[0] === 4) q4TotalSum += quarterValue;
                            else q1TotalSum += quarterValue;
                        }
                    });
                } else {
                    selectedYears.forEach((year, idx) => {
                        const quarterNum = selectedQuarters[idx % selectedQuarters.length];
                        const quarterValue = regionData[year]?.[quarterNum];
                        if (quarterValue !== undefined && typeof quarterValue === 'number' && !isNaN(quarterValue)) {
                            if (quarterNum === 2) q2TotalSum += quarterValue;
                            else if (quarterNum === 3) q3TotalSum += quarterValue;
                            else if (quarterNum === 4) q4TotalSum += quarterValue;
                            else q1TotalSum += quarterValue;
                        }
                    });
                }
            }
        }

        q1RegionSeriesData.push(q1TotalSum);
        q2RegionSeriesData.push(q2TotalSum);
        q3RegionSeriesData.push(q3TotalSum);
        q4RegionSeriesData.push(q4TotalSum);
    });

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

    chartRegionSeries.value = seriesData;

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

const chartRegionSeries = ref<any[]>([]);

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
        formatter: (value: number | null | undefined) => {
            if (value === null || value === undefined) {
                return '';
            }
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
        categories: fixedRegionOrder,
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

</script>

<template>
    <v-app>
        <v-container>
            <v-row>
                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                          
                            <v-row>
                               
                                <v-col cols="12" md="4">
                                    <v-container fluid>
                                        <v-row>
                                            <v-combobox v-model="selectyear" :items="year" label="เลือกปี" chips multiple></v-combobox>
                                        </v-row>
                                    </v-container>
                                </v-col>

                               
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
                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">กราฟเปรียบเทียบยอดเซ็นสัญญา แยกตามภูมิภาค</h3>
                            <h5 class="card-subtitle">{{ chartRegionSubtitle }}</h5>
                            <apexchart id="chart3" type="line" :options="chartRegionOptions" :series="chartRegionSeries" height="350" />
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </v-app>
</template>
