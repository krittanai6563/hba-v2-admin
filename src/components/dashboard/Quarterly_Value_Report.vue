<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { jsPDF } from 'jspdf';
import html2canvas from 'html2canvas';

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


        if (type === 'monthly') {
           
        } else if (type === 'quarterly') {
            updateChartQuarterData(jsonData);
            updateQuarterlyMonthComparisonData(jsonData);
        }
    } catch (err) {
        console.error('❌ Error fetching summary:', err);
    }
};





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
    chartQuarterOptions.value.xaxis.categories = categories;
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
        categories: categoryOrder,
        labels: {
            style: {
                fontSize: '12px',
                colors: '#6c757d'
            }
        },
        tickPlacement: 'on'
    },
    yaxis: {
        show: false
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

const chartMonthComparisonSeries = ref<any[]>([]);
const categories = ref<string[]>([]);

interface SummaryData {
    yearly_data: Record<string, Record<string, number>>;
    monthly_data: Record<string, Record<number, Record<string, number>>>;
    region_data?: Record<string, any>;
    quarterly_data?: Record<string, Record<number, Record<string, number>>>;
    quarterly_month_comparison?: Record<string, Record<number, Record<number, { รวม: number }>>>;
}

const updateQuarterlyMonthComparisonData = (data: SummaryData) => {
    const selectedYears = selectyear.value;
    const selectedQuarters = selectyearQuarters.value.map((q) => parseInt(q));

    const finalChartSeries: any[] = [];

    let updatedCategoriesForXAxis: string[] = [];

    const chartDataBySeriesName: Record<string, (number | null)[]> = {};

    if (selectedYears.length === 0 || selectedQuarters.length < 2) {
        chartMonthComparisonSeries.value = [];
        categories.value = [];
        return;
    }

    selectedQuarters.sort((a, b) => a - b);
    selectedYears.sort((a, b) => parseInt(a) - parseInt(b));

    const categoryDetailsByPositionalIndex: Record<number, { monthName: string; quarterNum: number; year: number; monthIndex: number }[]> =
        {};
    const allUniquePositionalIndicesWithData: Set<number> = new Set();

    selectedYears.forEach((year) => {
        selectedQuarters.forEach((quarterNum) => {
            const monthsInThisQuarter = getMonthsInQuarter(quarterNum);
            monthsInThisQuarter.forEach((monthIndex, positionalIndex) => {
                const monthName = Months[monthIndex - 1];
                const seriesNameForThisBar = `${monthName} (Q${quarterNum}) ปี ${year}`;

                if (!chartDataBySeriesName[seriesNameForThisBar]) {
                    chartDataBySeriesName[seriesNameForThisBar] = [];
                }

                const rawValue = data.quarterly_month_comparison?.[year]?.[quarterNum]?.[monthIndex]?.['รวม'];
                let value: number | null = null;
                if (typeof rawValue === 'number' && !isNaN(rawValue)) {
                    value = rawValue;
                }

                if (!categoryDetailsByPositionalIndex[positionalIndex]) {
                    categoryDetailsByPositionalIndex[positionalIndex] = [];
                }
                categoryDetailsByPositionalIndex[positionalIndex].push({
                    monthName,
                    quarterNum,
                    year: parseInt(year),
                    monthIndex
                });
                allUniquePositionalIndicesWithData.add(positionalIndex);

                chartDataBySeriesName[seriesNameForThisBar][positionalIndex] = value;
            });
        });
    });

    const sortedExistingPositionalIndices = Array.from(allUniquePositionalIndicesWithData).sort((a, b) => a - b);

    updatedCategoriesForXAxis = sortedExistingPositionalIndices.map((posIdx) => {
        const labelsForThisPos = categoryDetailsByPositionalIndex[posIdx]
            .sort((a, b) => {
                if (a.quarterNum !== b.quarterNum) {
                    return a.quarterNum - b.quarterNum;
                }
                return a.monthIndex - b.monthIndex;
            })
            .map((detail) => `${detail.monthName} (Q${detail.quarterNum})`);

        return labelsForThisPos.join(' / ');
    });

    categories.value = updatedCategoriesForXAxis;

    const uniqueSeriesNamesArray = Array.from(Object.keys(chartDataBySeriesName));

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

    sortedSeriesKeys.forEach((key) => {
        const dataForThisSeries: (number | null)[] = [];
        sortedExistingPositionalIndices.forEach((posIdx) => {
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
            }
        },
        zoom: {
            enabled: true,
            type: 'xy'
        },

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
            columnWidth: '50%',

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

            offsetX: 0,
            offsetY: 0
        },

        tickPlacement: 'on',
        axisBorder: {
            show: true,
            color: '#e0e0e0',
            height: 0,

            width: '100%',
            offsetX: 0,
            offsetY: 0
        },
        axisTicks: {
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

const fixedRegionOrder = ['กรุงเทพปริมณฑล', 'ภาคกลาง', 'ภาคเหนือ', 'ภาคตะวันออกเฉียงเหนือ', 'ภาคตะวันออก', 'ภาคตะวันตก', 'ภาคใต้'];




</script>

<template>
    <v-app>
        <v-container>
            <v-row>
               
            </v-row>

            <!-- Filter form for comparison -->
             
            <v-row>

                 <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <v-container fluid class="pa-0">
                                <v-alert density="compact" variant="tonal" color="info" class="mb-4">
                                    <b>วิธีใช้งาน:</b><br />
                                    - <b>เทียบไตรมาส (ในป
                                        เดียวกัน):</b> เลือก 1 ปี และเลือกหลายไตรมาส<br />
                                    - <b>เทียบปี (ในไตรมาสเดียวกัน):</b> เลือกหลายปี และเลือก 1 ไตรมาส
                                </v-alert>

                                <v-row>
                                    <v-col cols="12" md="6">
                                        <v-combobox
                                            v-model="selectyear"
                                            :items="year"
                                            label="เลือกปี"
                                            chips
                                            multiple
                                            clearable
                                            variant="outlined"
                                            density="comfortable"
                                        ></v-combobox>
                                    </v-col>
                                    <v-col cols="12" md="6">
                                        <v-combobox
                                            v-model="selectyearQuarters"
                                            :items="Quarters.map(q => `${q}`)"
                                            label="เลือกไตรมาส"
                                            chips
                                            multiple
                                            clearable
                                            variant="outlined"
                                            density="comfortable"
                                        ></v-combobox>
                                    </v-col>
                                </v-row>
                            </v-container>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">กราฟเปรียบเทียบยอดเซ็นสัญญา แยกตามมูลค่าบ้าน (ไตรมาส)</h3>
                            <h5 class="card-subtitle">{{ chartQuarterSubtitle }}</h5>
                            <apexchart id="chart2" type="line" :options="chartQuarterOptions" :series="chartQuarterSeries" height="350" />
                        </v-card-text>
                    </v-card>
                </v-col>              
            </v-row>
        </v-container>
    </v-app>
</template>
