<script setup lang="ts">
import { ref, watch, onMounted, defineProps } from 'vue';


const props = defineProps<{
    monthName: string
    quarter: number
}>()


const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user';

const dialogOpen = ref(false);


const currentYear = new Date().getFullYear() + 543;
const selectedBuddhistYear = ref(currentYear);
const yearOptions = Array.from({ length: 7 }, (_, i) => currentYear - i);

const today = new Date();
const currentMonthNumber = today.getMonth() + 1;

interface MonthItem {
    label: string;
    quarter: number;
    monthNumber: number;
    index: number;
}

const monthsList = ref<MonthItem[]>([]);

const isFirstLoad = ref(true);

watch(
    selectedBuddhistYear,
    (newYear) => {
        const gregorianYear = newYear - 543;
        const isCurrentYear = today.getFullYear() === gregorianYear;
        const monthLimit = isCurrentYear ? today.getMonth() + 1 : 12;

        monthsList.value = Array.from({ length: monthLimit }, (_, i) => {
            const date = new Date(gregorianYear, i);
            const month = date.toLocaleString('th-TH', { month: 'long' });
            return {
                label: `${month} ${newYear}`,
                quarter: Math.floor(i / 3) + 1,
                monthNumber: i + 1,
                index: i
            };
        });

        if (isFirstLoad.value && isCurrentYear) {
            setTimeout(() => {
                setActiveMonthPanel(newYear);
                isFirstLoad.value = false;
            }, 0);
        }
    },
    { immediate: true }
);

const panelQ1 = ref<number[]>([]);
const panelQ2 = ref<number[]>([]);
const panelQ3 = ref<number[]>([]);
const panelQ4 = ref<number[]>([]);

const panelModels: Record<number, ReturnType<typeof ref<number[]>>> = {
    1: panelQ1,
    2: panelQ2,
    3: panelQ3,
    4: panelQ4
};

const getPanelModel = (quarter: number): number[] => {
    return panelModels[quarter]?.value ?? [];
};

const updatePanelModel = (quarter: number, val: unknown) => {
    if (typeof val === 'number') {
        panelModels[quarter].value = [val];
    } else if (Array.isArray(val) && val.every((i) => typeof i === 'number')) {
        panelModels[quarter].value = val;
    } else {
        console.warn('Invalid panel value:', val);
    }
};

const regionsData = ref([
    {
        name: 'กรุงเทพปริมณฑล',
        contractStatus: 'has',
        rows: [
            { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, area: 0, errorMessage: '', touched: false }
        ]
    },
    {
        name: 'ภาคเหนือ',
        contractStatus: 'has',
        rows: [
            { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, area: 0, errorMessage: '', touched: false }
        ]
    },
    {
        name: 'ภาคตะวันออกเฉียงเหนือ',
        contractStatus: 'has',
        rows: [
            { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, area: 0, errorMessage: '', touched: false }
        ]
    },
    {
        name: 'ภาคกลาง',
        contractStatus: 'has',
        rows: [
            { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, area: 0, errorMessage: '', touched: false }
        ]
    },
    {
        name: 'ภาคตะวันออก',
        contractStatus: 'has',
        rows: [
            { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, area: 0, errorMessage: '', touched: false }
        ]
    },
    {
        name: 'ภาคใต้',
        contractStatus: 'has',
        rows: [
            { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, area: 0, errorMessage: '', touched: false }
        ]
    },
    {
        name: 'ภาคตะวันตก',
        contractStatus: 'has',
        rows: [
            { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, area: 0, errorMessage: '', touched: false }
        ]
    }
]);



function setActiveMonthPanel(year: number) {
    if (year !== currentYear) return;

    const currentQuarter = Math.floor((currentMonthNumber - 1) / 3) + 1;
    const quarterMonths = monthsList.value.filter((m) => m.quarter === currentQuarter);
    const indexInQuarter = quarterMonths.findIndex((m) => m.monthNumber === currentMonthNumber);

    if (indexInQuarter !== -1) {
        panelModels[currentQuarter].value = [indexInQuarter];
    }
}


interface ContractDetail {
    unit: number
    value: number
    area: number
    price_per_sqm?: number
}
type RegionData = {
    [type: string]: ContractDetail
}
type ContractData = {
    [region: string]: RegionData
}

const contractTypes = [
    'ไม่เกิน 2.50 ล้านบาท',
    '2.51 - 5 ล้านบาท',
    '5.01 - 10 ล้านบาท',
    '10.01 - 20 ล้านบาท',
    '20.01 ล้านขึ้นไป'
]

const regions = [
    'กรุงเทพปริมณฑล',
    'ภาคเหนือ',
    'ภาคตะวันออกเฉียงเหนือ',
    'ภาคกลาง',
    'ภาคตะวันออก',
    'ภาคใต้',
    'ภาคตะวันตก'
]

const contractData = ref<ContractData>({})


function convertMonthToNumber(monthLabel: string): number {
    const months = [
        'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ]
    return months.findIndex(m => monthLabel.includes(m)) + 1
}
function extractBuddhistYear(monthLabel: string): number {
    const match = monthLabel.match(/\d{4}/)
    const year = match ? parseInt(match[0]) : null
    return year ? year : new Date().getFullYear() + 543
}


function getCell(region: string, type: string, field: keyof ContractDetail): string {
    const r = region.trim().toLowerCase()
    const t = type.trim().toLowerCase()

    const data = contractData.value ?? {}
    for (const regKey in data) {
        if (regKey.trim().toLowerCase() === r) {
            const regionObj = data[regKey]
            for (const typeKey in regionObj) {
                if (typeKey.trim().toLowerCase() === t) {
                    const val = regionObj[typeKey]?.[field]
                    return val == null ? '-' : typeof val === 'number' ? val.toLocaleString() : String(val)
                }
            }
        }
    }
    return '-'
}


function getRegionTotal(region: string, field: keyof ContractDetail): string {
    const r = region.trim().toLowerCase()
    const data = contractData.value ?? {}

    for (const regKey in data) {
        if (regKey.trim().toLowerCase() === r) {
            return contractTypes.reduce((total, type) => {
                const t = type.trim().toLowerCase()
                for (const typeKey in data[regKey]) {
                    if (typeKey.trim().toLowerCase() === t) {
                        total += data[regKey][typeKey]?.[field] ?? 0
                        break
                    }
                }
                return total
            }, 0).toLocaleString()
        }
    }
    return '0'
}


const fetchContractData = async () => {
    const buddhistYear = extractBuddhistYear(props.monthName)
    const monthNumber = convertMonthToNumber(props.monthName)

    const payload: Record<string, any> = {
        role: userRole,
        buddhist_year: buddhistYear,
        month_number: monthNumber,
        quarter: props.quarter
    }

    if (userId) {
        payload.user_id = userId
    }



    console.log('📤 Sending payload:', payload)

    try {
        const response = await fetch('https://88ae10127f9b.ngrok-free.app/package/backend/get_contract_data.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })

        const rawData = await response.json()
        console.log('📦 Contract Data from API:', rawData)

        if (rawData.error) {
            console.error('⛔️', rawData.error)
            return
        }

        const trimmedData: ContractData = {}
        for (const region in rawData) {
            const r = region.trim()
            trimmedData[r] = {}
            for (const type in rawData[region]) {
                trimmedData[r][type.trim()] = rawData[region][type]
            }
        }

        contractData.value = trimmedData
    } catch (err) {
        console.error('❌ Error fetching contract data:', err)
    }
}

onMounted(fetchContractData)

</script>


<template>
    <v-row>
        <v-card elevation="10">
            <v-card-text>

                <div class="v-row">
                    <div class="v-col-md-6 v-col-12">
                        <div class="d-flex align-center">
                            <div>
                                <h3 class="card-title mb-1">สรุปข้อมูลยอดเซ็นสัญญา ประจำปี {{ selectedBuddhistYear }}
                                </h3>
                                <h5 class="card-subtitle" style="text-align: left;">ข้อมูลแสดงเป็นรายเดือน
                                </h5>
                            </div>
                        </div>
                    </div>

                    <div class="v-col-md-6 v-col-12 text-right">
                        <div class="d-flex  justify-end v-col-md-12 v-col-lg-12 v-col-12 ">
                            <v-select v-model="selectedBuddhistYear" label="เลือกปี" :items="yearOptions"
                                class="mr-4"></v-select>
                            <v-btn class=" text-primary   v-btn--size-large ">
                                <div class="text-none font-weight-regular muted">Export to CSV</div>
                            </v-btn>
                        </div>
                    </div>
                </div>

                <div class="v-col-md-12"></div>

                <div v-for="quarter in [1, 2, 3, 4]" :key="quarter" class="mb-6">
                    <h3 class="text-h6 mb-2" style="font-weight: 500; color: #1b84ff"
                        v-if="selectedBuddhistYear < currentYear || quarter <= Math.ceil(currentMonthNumber / 3)">
                        ยอดเซ็นสัญญาไตรมาส {{ quarter }}
                    </h3>

                    <v-expansion-panels accordion :model-value="getPanelModel(quarter)[0]"
                        @update:model-value="(val) => updatePanelModel(quarter, val)">
                        <v-expansion-panel v-for="month in monthsList.filter((m) => m.quarter === quarter)"
                            :key="month.index"
                            :hide-actions="month.monthNumber === currentMonthNumber && selectedBuddhistYear === currentYear">
                            <v-expansion-panel-title>
                                <div class="d-flex justify-space-between align-center w-100">
                                    <span>ยอดเซ็นสัญญาประจำเดือน {{ month.label }}</span>
                   <template v-if="
    month.monthNumber === currentMonthNumber &&
    selectedBuddhistYear === currentYear &&
    (userRole !== 'master' && userRole !== 'admin')
">
    <v-dialog v-model="dialogOpen" width="80%">
    </v-dialog>
</template>


                                </div>
                            </v-expansion-panel-title>

                            <v-expansion-panel-text>
                                <div class="v-table v-theme--BLUE_THEME v-table--density-default month-table">
                                    <div class="v-table__wrapper">
                                        <table>
                                            <thead style="background-color: #F5F5F5;">
                                                <tr>
                                                    <th class="text-h6"></th>
                                                    <th class="text-h6" :colspan="regions.length"
                                                        style="text-align: center; border-bottom: 2px solid #00A6D4;">
                                                        ยอดเซ็นสัญญาประจำเดือน {{ props.monthName }} ไตรมาสที่ 111222{{
                                                        props.quarter }}
                                                    </th>
                                                </tr>
                                                <tr>
                                                    <th></th>
                                                    <th v-for="region in regions" :key="region" class="text-p"
                                                        style="font-size: 13px;">
                                                        {{ region }}
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template v-for="(label, i) in contractTypes" :key="i">

                                                    <tr class="month-item" style="background-color: #fcf8ff;">
                                                        <td>
                                                            <h6 class="text-p"
                                                                style="font-size: 12px; font-weight: 600; color: #725AF2;">
                                                                {{ label }}</h6>
                                                        </td>
                                                        <td v-for="region in regions" :key="region">
                                                            <h6 class="text-subtitle-1"></h6>
                                                        </td>
                                                    </tr>


                                                    <tr class="month-item">
                                                        <td>
                                                            <h6 class="text-p"
                                                                style="font-size: 12px; font-weight: 400;">จำนวนหลัง
                                                            </h6>
                                                        </td>
                                                        <td v-for="region in regions" :key="region">
                                                            <h6 class="text-p"
                                                                style="font-size: 13px; font-weight: 400;">{{
                                                                getCell(region, label, 'unit') }}</h6>
                                                        </td>
                                                    </tr>


                                                    <tr class="month-item">
                                                        <td>
                                                            <h6 class="text-p"
                                                                style="font-size: 12px; font-weight: 400;">มูลค่ารวม
                                                            </h6>
                                                        </td>
                                                        <td v-for="region in regions" :key="region">
                                                            <h6 class="text-p"
                                                                style="font-size: 13px; font-weight: 400;">{{
                                                                getCell(region, label, 'value') }}</h6>
                                                        </td>
                                                    </tr>


                                                    <tr class="month-item">
                                                        <td>
                                                            <h6 class="text-p"
                                                                style="font-size: 12px; font-weight: 400;">พื้นที่ใช้สอย
                                                            </h6>
                                                        </td>
                                                        <td v-for="region in regions" :key="region">
                                                            <h6 class="text-p"
                                                                style="font-size: 13px; font-weight: 400;">{{
                                                                getCell(region, label, 'area') }}</h6>
                                                        </td>
                                                    </tr>


                                                    <tr class="month-item">
                                                        <td>
                                                            <h6 class="text-p"
                                                                style="font-size: 12px; font-weight: 400;">ราคาเฉลี่ย
                                                                ตร.ม</h6>
                                                        </td>
                                                        <td v-for="region in regions" :key="region">
                                                            <h6 class="text-p"
                                                                style="font-size: 13px; font-weight: 400;">
                                                                <h6 class="text-p"
                                                                    style="font-size: 13px; font-weight: 400;">
                                                                    {{ getCell(region, label, 'price_per_sqm') }}
                                                                </h6>
                                                            </h6>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </v-expansion-panel-text>
                        </v-expansion-panel>
                    </v-expansion-panels>
                </div>
            </v-card-text>
        </v-card>
    </v-row>
</template>

<style scoped>
.text-h6 {
    font-size: 18px;
}
</style>
