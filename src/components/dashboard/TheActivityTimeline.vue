<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';

const currentYear = new Date().getFullYear() + 543;

const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user'; 

const summaryData = ref<Record<string, Record<string, Record<string, number>>>>({});

const visibleYears = computed(
    () => Array.from({ length: 4 }, (_, i) => (currentYear - 3 + i).toString()) 
);

const priceRanges = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป'];

const dataTypes = ['จำนวนหลัง', 'มูลค่ารวม', 'พื้นที่ใช้สอย', 'ราคาเฉลี่ย/ตร.ม.'] as const;

const typeMap: Record<(typeof dataTypes)[number], 'unit' | 'value' | 'area' | 'price_per_sqm'> = {
    จำนวนหลัง: 'unit',
    มูลค่ารวม: 'value',
    พื้นที่ใช้สอย: 'area',
    'ราคาเฉลี่ย/ตร.ม.': 'price_per_sqm'
};

const fetchSummary = async () => {
    if (!userId) return;
    try {
        const res = await fetch('https://d2e03fa78899.ngrok-free.app/package/backend/sales_overview.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: userId,
                year: currentYear,
                role: userRole
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
    let total = visibleYears.value.reduce((sum: number, year: string) => {
        return sum + (summaryData.value[year]?.[label]?.[type] || 0);
    }, 0);

    const isDecimal = type === 'price_per_sqm';

    if (isDecimal) {
        total = Math.floor(total * 100) / 100;
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
                    <h3 class="card-title">สรุปยอดเซ็นสัญญารวมย้อนหลัง</h3>
                    <h5 class="card-subtitle">ข้อมูลรวมทั้งปี แสดงย้อนหลัง 4 ปี</h5>
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
                                    :colspan="visibleYears.length + 1"
                                    style="text-align: center; border-bottom: 2px solid #00a6d4"
                                >
                                    รวมข้อมูลรายปีย้อนหลัง 4 ปี
                                </th>
                            </tr>
                            <tr>
                                <th class="text-h6"></th>
                                <th v-for="year in visibleYears" :key="year" class="text-h6" style="text-align: center">{{ year }}</th>
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
                                    <td v-for="year in visibleYears" :key="year + '-' + type">
                                        <h6 class="text-subtitle-1 text-center">
                                            {{ formatNumber(summaryData[year]?.[label]?.[typeMap[type]] || 0) }}
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


