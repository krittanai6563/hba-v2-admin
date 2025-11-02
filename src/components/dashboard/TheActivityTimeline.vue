<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';

const currentDate = new Date();
const currentYear = currentDate.getFullYear() + 543;

const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user'; 

// แก้ไขปัญหา TSX Ambiguity โดยเพิ่มเครื่องหมาย ,
type SummaryData = Record<string, Record<string, Record<string, number>>>; 
const summaryData = ref<SummaryData>({});

const priceRanges = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป'];

const dataTypes = ['จำนวนหลัง', 'มูลค่ารวม', 'พื้นที่ใช้สอย', 'ราคาเฉลี่ย/ตร.ม.'] as const;

const typeMap: Record<(typeof dataTypes)[number], 'unit' | 'value' | 'area' | 'price_per_sqm'> = {
    จำนวนหลัง: 'unit',
    มูลค่ารวม: 'value',
    พื้นที่ใช้สอย: 'area',
    'ราคาเฉลี่ย/ตร.ม.': 'price_per_sqm'
};

const monthsThaiFull = [
    "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
    "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
];

type VisibleMonth = {
    header: string;
    dataKey: string;
    year: string;
    month: string;
};

const visibleMonths = computed((): VisibleMonth[] => {
    const monthsArray: VisibleMonth[] = [];
    const numMonths = 4;
    const currentMonthIndex = currentDate.getMonth(); 
    
    for (let i = numMonths - 1; i >= 0; i--) {
        const date = new Date(currentDate.getFullYear(), currentMonthIndex - i, 1);
        const year = date.getFullYear() + 543;
        const monthNum = date.getMonth(); 
        const yearKey = year.toString();
        const monthKey = (monthNum + 1).toString();
        
        monthsArray.push({
            header: `${monthsThaiFull[monthNum]} ${yearKey}`, 
            dataKey: `${yearKey}-${monthKey}`, 
            year: yearKey,
            month: monthKey
        });
    }
    return monthsArray;
});

const tableTitleRange = computed(() => {
    const range = visibleMonths.value;
    if (range.length < 4) return 'ข้อมูลสรุป 4 เดือนล่าสุด';
    
    const start = range[0];
    const end = range[3];

    if (start.year !== end.year) {
         return `ข้อมูลรวมย้อนหลัง (${start.header} - ${end.header})`;
    }

    return `ข้อมูลรวมย้อนหลัง (${start.header.split(' ')[0]} - ${end.header})`;
});


const fetchSummary = async () => {
    if (!userId) return;
    
    const firstMonth = visibleMonths.value[0];
    const lastMonth = visibleMonths.value[3];
    
    try {
        const res = await fetch(' https://uat.hba-sales.org/backend/sales_overview.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: userId,
                role: userRole,
                start_month: firstMonth.month, 
                start_year: firstMonth.year,
                end_month: lastMonth.month,
                end_year: lastMonth.year,
            })
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        summaryData.value = await res.json();
        console.log( summaryData.value)
    } catch (err) {
        console.error('Error fetching summary:', err);
    }
};

onMounted(fetchSummary);

const formatNumber = (value: number, isDecimal: boolean = false): string => {
    return value.toLocaleString('th-TH', {
        minimumFractionDigits: isDecimal ? 2 : 0,
        maximumFractionDigits: isDecimal ? 2 : 2
    });
};

const getRowTotal = (label: string, type: 'unit' | 'value' | 'area' | 'price_per_sqm'): string => {
    let total = visibleMonths.value.reduce((sum: number, monthObj) => {
        return sum + (summaryData.value[monthObj.dataKey]?.[label]?.[type] || 0);
    }, 0);

    const isDecimal = type === 'price_per_sqm';

    if (isDecimal) {
        if (type === 'price_per_sqm') {
             const totalValue = visibleMonths.value.reduce((sum, monthObj) => sum + (summaryData.value[monthObj.dataKey]?.[label]?.['value'] || 0), 0);
             const totalArea = visibleMonths.value.reduce((sum, monthObj) => sum + (summaryData.value[monthObj.dataKey]?.[label]?.['area'] || 0), 0);
             
             total = totalArea > 0 ? totalValue / totalArea : 0;

        } else {
             total = Math.floor(total * 100) / 100;
        }
    }

    return formatNumber(total, isDecimal);
};
</script>

<main>

</main>
<template>
    <v-row>
        <v-col cols="12">
            <div class="v-row">
                <div class="v-col-md-8 text-left">
                    <h3 class="card-title">สรุปยอดเซ็นสัญญารวมย้อนหลัง 4 เดือน</h3>
                    <h5 class="card-subtitle">{{ tableTitleRange }}</h5>
                </div>
            </div>

            <div class="v-table v-theme--BLUE_THEME v-table--density-default mt-4">
                <div class="v-table__wrapper">
                    <table>
                        <thead style="background-color: #f5f5f5">
                            <tr>
                                <th class="text-h6">Financial Summary</th>
                                <th
                                    class="text-h6"
                                    :colspan="visibleMonths.length + 1"
                                    style="text-align: center; border-bottom: 2px solid #00a6d4"
                                >
                                    รวมข้อมูลรายเดือนย้อนหลัง 4 เดือน
                                </th>
                            </tr>
                            <tr>
                                <th class="text-h6"></th>
                                <th v-for="monthObj in visibleMonths" :key="monthObj.dataKey" class="text-h6" style="text-align: center">{{ monthObj.header }}</th>
                                <th class="text-h6" style="background-color: #fff3e0">รวม</th>
                            </tr>
                        </thead>

                        <tbody>
                            <template v-for="label in priceRanges" :key="label">
                                <tr style="background-color: #fcf8ff">
                                    <td colspan="100%">
                                        <h6 class="text-p" style="font-size: 14px; font-weight: 600; color: #725af2">
                                            {{ label }}
                                        </h6>
                                    </td>
                                </tr>
                                <tr v-for="type in dataTypes" :key="label + '-' + type">
                                    <td>
                                        <h6 class="text-subtitle-1">{{ type }}</h6>
                                    </td>
                                    <td v-for="monthObj in visibleMonths" :key="monthObj.dataKey + '-' + type">
                                        <h6 class="text-subtitle-1 text-center">
                                            {{ formatNumber(summaryData[monthObj.dataKey]?.[label]?.[typeMap[type]] || 0) }}
                                        </h6>
                                    </td>
                                    <td style="background-color: #fff3e0">
                                        {{ getRowTotal(label, typeMap[type]) }}
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </v-col>
    </v-row>
</template>

<style scoped>
.card-title {
    font-size: 20px;
    font-weight: 600;
}

.card-subtitle {
    font-size: 14px;
    color: #6b7280;
}

.text-subtitle-1 {
    font-size: 13px;
}
</style>
