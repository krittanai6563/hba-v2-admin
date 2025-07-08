<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import * as XLSX from 'xlsx';
import ExcelJS from 'exceljs';
import { TomlIcon } from 'vue-tabler-icons';

const currentYear = new Date().getFullYear() + 543;
const currentMonth = new Date().getMonth() + 1;
const selectedYear = ref(currentYear.toString());
const yearOptions = Array.from({ length: 7 }, (_, i) => (currentYear - i).toString());

const summaryData = ref<Record<string, Record<string, Record<string, number>>>>({});
const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user';

const priceRanges = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป'];
const dataTypes = ['จำนวนหลัง', 'มูลค่ารวม', 'พื้นที่ใช้สอย', 'ราคาเฉลี่ย/ตร.ม.'] as const;

const typeMap: Record<(typeof dataTypes)[number], 'unit' | 'value' | 'area' | 'price_per_sqm'> = {
  'จำนวนหลัง': 'unit',
  'มูลค่ารวม': 'value',
  'พื้นที่ใช้สอย': 'area',
  'ราคาเฉลี่ย/ตร.ม.': 'price_per_sqm'
};


const getMaxQuarter = () => {
  if (selectedYear.value !== currentYear.toString()) return 4;
  if (currentMonth <= 3) return 1;
  if (currentMonth <= 6) return 2;
  if (currentMonth <= 9) return 3;
  return 4;
};

const fetchSummary = async () => {
  if (!userId) return;
  try {
   const res = await fetch(`https://6e9fdf451a56.ngrok-free.app/package/backend/quarter_summary.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        user_id: userId,
        year: selectedYear.value,
        role: userRole
      })


    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    summaryData.value = await res.json();
  } catch (err) {
    console.error('Error fetching summary:', err);
  }
};

watch(selectedYear, fetchSummary);
onMounted(fetchSummary);

const visibleQuarters = computed(() => {
  const maxQ = getMaxQuarter();
  return ['Q1', 'Q2', 'Q3', 'Q4'].filter((_, i) => i < maxQ).map(q => `${q} ${selectedYear.value}`);
});


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
                download: true, 
            },
        },
        
        responsive: [
            {
                breakpoint: 1000,  
                options: {
                    chart: {
                        width: '100%', 
                    },
                },
            },
        ],
    },
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '40%', 
            dataLabels: {
                position: 'top',
                offsetY: 0,
            },
        },
        line: {
            dataLabels: {
                position: 'top',
                offsetY: 0,
            },
            curve: 'smooth',
        },
    },
    dataLabels: {
        enabled: true,  
        position: 'top',
        offsetY: -13,
        style: {
            fontSize: '10px',
        },
        formatter: (value: number) => {  
            return value >= 1000 ? value.toLocaleString('th-TH') : value.toString();
        }
    },
    stroke: {
        width: [2, 2, 4],  
        curve: 'smooth',
    },
    grid: {
        show: true,
        strokeDashArray: 4, 
        borderColor: 'rgba(0, 0, 0, 0.1)',  
    },
    xaxis: {
        categories: visibleQuarters.value,  
        labels: {
            rotate: -45, 
            style: {
                fontSize: '12px', 
                colors: '#6c757d',  
            },
        },
    },
    yaxis: [
        {
            seriesName: 'จำนวนหลัง',
            axisTicks: { show: false },
            axisBorder: {
                show: false,
                color: '#008FFB',
            },
            labels: {
                show: false,  
                style: {
                    colors: '#008FFB',  
                },
            },
            tooltip: {
                enabled: false,
            },
        },
        {
            seriesName: 'พื้นที่ใช้สอย',
            opposite: false,
            axisTicks: { show: false },
            axisBorder: {
                show: false,
                color: '#00E396',
            },
            labels: {
                show: false,  
                style: {
                    colors: '#00E396',  
                },
            },
        },
        {
            seriesName: 'มูลค่ารวม',
            opposite: false,
            axisTicks: { show: true },
            axisBorder: {
                show: false,
                color: '#FEB019',
            },
            labels: {
                show: false,  
                style: {
                    colors: '#FEB019',  
                },
            },
        },
    ],
    tooltip: {
        fixed: {
            enabled: false,
            position: 'topLeft',
            offsetY: 0,
            offsetX: 0,
        },
    },
    legend: {
        horizontalAlign: 'center',
        offsetX: 0,
    },
});


const chartSeries = computed(() => {
    return [
        {
            name: 'จำนวนหลัง',
            type: 'column',
            data: visibleQuarters.value.map((q) => {
                const [quarter] = q.split(' ');
                return parseFloat(
                    priceRanges.reduce((sum, price) => {
                        const value = summaryData.value[quarter]?.[price]?.['unit'] || 0;
                        return sum + value;
                    }, 0).toFixed(2)
                );
            }),
            color: '#F9C80E',
        },
        {
            name: 'พื้นที่ใช้สอย',
            type: 'column',
            data: visibleQuarters.value.map((q) => {
                const [quarter] = q.split(' ');
                return parseFloat(
                    priceRanges.reduce((sum, price) => {
                        const value = summaryData.value[quarter]?.[price]?.['area'] || 0;
                        return sum + value;
                    }, 0).toFixed(2)
                );
            }),
            color: '#2983FF',
        },
        {
            name: 'มูลค่ารวม',
            type: 'line',
            data: visibleQuarters.value.map((q) => {
                const [quarter] = q.split(' ');
                return parseFloat(
                    priceRanges.reduce((sum, price) => {
                        const value = summaryData.value[quarter]?.[price]?.['value'] || 0;
                        return sum + value;
                    }, 0).toFixed(2)
                );
            }),
            color: '#D7263D',
        },

        
    ];
});





const formatNumber = (value: number, isDecimal = false) => {
  return isDecimal
    ? value.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : value.toLocaleString('th-TH');
};


const getQuarterTotal = (quarterLabel: string, type: 'unit' | 'value' | 'area') => {
  const [q] = quarterLabel.split(' ');
  const total = priceRanges.reduce((sum, price) => {
    return sum + (summaryData.value[q]?.[price]?.[type] || 0);
  }, 0);
  return formatNumber(total);
};

const getRowTotal = (label: string, type: 'unit' | 'value' | 'area' | 'price_per_sqm') => {

  return formatNumber(
    visibleQuarters.value.reduce((sum, q) => {
      const [quarter] = q.split(' ');
      return sum + (summaryData.value[quarter]?.[label]?.[type] || 0);
    }, 0)
  );
};

const getTotalYearTotal = (type: 'unit' | 'value' | 'area') => {
  return formatNumber(
    visibleQuarters.value.reduce((sumQ, q) => {
      const [quarter] = q.split(' ');
      return (
        sumQ +
        priceRanges.reduce((sumP, price) => {
          return sumP + (summaryData.value[quarter]?.[price]?.[type] || 0);
        }, 0)
      );
    }, 0)
  );
};




const exportToExcel = () => {
  const wb = XLSX.utils.book_new();
  const ws_data: (string | number)[][] = [];
  const merges: { s: { r: number, c: number }, e: { r: number, c: number } }[] = [];
  const colStyles: { [key: string]: { wch?: number } } = {};
  const cellStyles: { [key: string]: XLSX.CellObject } = {}; 

  let currentRowIndex = 0;

  ws_data.push([`รายงานเปรียบเทียบยอดเซ็นสัญญา ประจำปี ${selectedYear.value}`]);
  merges.push({ s: { r: currentRowIndex, c: 0 }, e: { r: currentRowIndex, c: visibleQuarters.value.length + 1 } });
  

  const titleCellAddress = XLSX.utils.encode_cell({ r: currentRowIndex, c: 0 });
  cellStyles[titleCellAddress] = {
    v: ws_data[currentRowIndex][0], 
    t: 's', 
    s: {
      fill: { fgColor: { rgb: 'FF008000' } }, 
      font: { bold: true, color: { rgb: 'FFFFFFFF' }, name: 'Angsana New' },
      alignment: { horizontal: 'center', vertical: 'center' }
    }
  };
  currentRowIndex++;

 
  ws_data.push(['(หน่วย : ล้านบาท)', ...visibleQuarters.value, 'รวม']);
 
  const secondHeaderRowIndex = currentRowIndex;
  ws_data[secondHeaderRowIndex].forEach((_, colIndex) => {
    const cellAddress = XLSX.utils.encode_cell({ r: secondHeaderRowIndex, c: colIndex });
    cellStyles[cellAddress] = {
      v: ws_data[secondHeaderRowIndex][colIndex],
      t: 's',
      s: {
        fill: { fgColor: { rgb: 'FFF0F0F0' } },
        font: { bold: true, name: 'Angsana New' },
        alignment: { horizontal: 'center', vertical: 'center' }
      }
    };
  });
  

  for (let i = 1; i <= visibleQuarters.value.length; i++) {
    const cellAddress = XLSX.utils.encode_cell({ r: secondHeaderRowIndex, c: i });
    if (cellStyles[cellAddress]) {
      cellStyles[cellAddress].s.border = { bottom: { style: 'thin', color: { rgb: 'FF00A6D4' } } }; // Blue border
    } else {
      cellStyles[cellAddress] = {
        v: ws_data[secondHeaderRowIndex][i],
        t: 's',
        s: {
          fill: { fgColor: { rgb: 'FFF0F0F0' } }, 
          font: { bold: true, name: 'Angsana New' },
          alignment: { horizontal: 'center', vertical: 'center' },
          border: { bottom: { style: 'thin', color: { rgb: 'FF00A6D4' } } }
        }
      };
    }
  }
  currentRowIndex++;

  
  priceRanges.forEach((priceRange) => {
    ws_data.push([priceRange, ...Array(visibleQuarters.value.length + 1).fill('')]);
    merges.push({
      s: { r: currentRowIndex, c: 0 },
      e: { r: currentRowIndex, c: visibleQuarters.value.length + 1 }
    });

    const priceRangeCellAddress = XLSX.utils.encode_cell({ r: currentRowIndex, c: 0 });
    cellStyles[priceRangeCellAddress] = {
      v: ws_data[currentRowIndex][0],
      t: 's',
      s: {
        fill: { fgColor: { rgb: 'FFFCF8FF' } },
        font: { bold: true, color: { rgb: 'FF725AF2' }, name: 'Angsana New' },
        alignment: { horizontal: 'left' }
      }
    };
    currentRowIndex++;

    dataTypes.forEach((type) => {
      const row: (string | number)[] = [type];
      let total = 0;

      visibleQuarters.value.forEach((q) => {
        const quarter = q.split(' ')[0];
        const val = summaryData.value[quarter]?.[priceRange]?.[typeMap[type]] || 0;
        total += val;

        row.push(val);
      });

      row.push(total);
      ws_data.push(row);

      const dataRowIndex = currentRowIndex;
      const typeLabelCellAddress = XLSX.utils.encode_cell({ r: dataRowIndex, c: 0 });
      cellStyles[typeLabelCellAddress] = {
        v: ws_data[dataRowIndex][0],
        t: 's',
        s: { font: { name: 'Angsana New' } }
      };


      ws_data[dataRowIndex].forEach((cellValue, colIndex) => {
        if (colIndex > 0) { 
          const cellAddress = XLSX.utils.encode_cell({ r: dataRowIndex, c: colIndex });
          const isTotalColumn = colIndex === ws_data[dataRowIndex].length - 1; 

          const val = cellValue as number;
          const formattedVal = type === 'ราคาเฉลี่ย/ตร.ม.'
            ? val.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : val.toLocaleString('th-TH');

          cellStyles[cellAddress] = {
            v: formattedVal,
            t: 's', 
            s: {
              font: { name: 'Angsana New' },
              alignment: { horizontal: 'right' },
              fill: isTotalColumn ? { fgColor: { rgb: 'FFFFF3E0' } } : undefined
            }
          };
        }
      });
      currentRowIndex++;
    });
    ws_data.push([]); 
    currentRowIndex++;
  });


  const totalSummaryRowStyles: XLSX.Style = {
    fill: { fgColor: { rgb: 'FFFCF8FF' } }, // Light purple
    font: { bold: true, color: { rgb: 'FFF8285A' }, name: 'Angsana New' }, 
    alignment: { horizontal: 'left' }
  };

  const totalValueCellStyles: XLSX.Style = {
    font: { bold: true, color: { rgb: 'FFF8285A' }, name: 'Angsana New' }, 
    alignment: { horizontal: 'right' },
    fill: { fgColor: { rgb: 'FFFFF3E0' } } 
  };


  ['unit', 'value', 'area'].forEach(typeKey => {
    const totalRowLabel = dataTypes.find(key => typeMap[key] === typeKey) + ' (รวม)';
    const row: (string | number)[] = [totalRowLabel];
    let yearTotal = 0;

    visibleQuarters.value.forEach((q) => {
      const quarter = q.split(' ')[0];
      const quarterTotal = priceRanges.reduce((sum, price) => {
        return sum + (summaryData.value[quarter]?.[price]?.[typeKey as 'unit' | 'value' | 'area'] || 0);
      }, 0);
      row.push(quarterTotal); // Push raw number for calculations
      yearTotal += quarterTotal;
    });
    row.push(yearTotal); // Overall year total

    ws_data.push(row);

    // Apply styles to the total row
    const totalRowIndex = currentRowIndex;
    // Label cell style
    const labelCellAddress = XLSX.utils.encode_cell({ r: totalRowIndex, c: 0 });
    cellStyles[labelCellAddress] = {
      v: ws_data[totalRowIndex][0],
      t: 's',
      s: totalSummaryRowStyles
    };

    // Quarter totals and year total cells style
    for (let i = 1; i < row.length; i++) {
      const cellAddress = XLSX.utils.encode_cell({ r: totalRowIndex, c: i });
      const val = ws_data[totalRowIndex][i] as number;
      const formattedVal = formatNumber(val); // Format numbers
      cellStyles[cellAddress] = {
        v: formattedVal,
        t: 's',
        s: totalValueCellStyles // Apply the light orange fill and red bold font
      };
    }
    currentRowIndex++;
  });


  const sheet = XLSX.utils.aoa_to_sheet(ws_data);

  // Apply all collected cell styles to the sheet
  for (const cellAddress in cellStyles) {
    sheet[cellAddress] = cellStyles[cellAddress];
  }

  // Set column widths
  sheet['!cols'] = [
    { wch: 35 }, // Column A (labels)
    ...visibleQuarters.value.map(() => ({ wch: 15 })), // Quarter columns
    { wch: 15 }, // Total column
  ];

  
  sheet['!merges'] = merges;

  XLSX.utils.book_append_sheet(wb, sheet, 'ตารางรายงาน');
  XLSX.writeFile(wb, `รายงานแบ่งตามไตรมาส_${selectedYear.value}.xlsx`);
};
</script>



<template>
  <v-row>
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

    <v-col cols="12">
      <VCard elevation="10">
        <v-card-text>

          <div class="v-row">
            <div class="v-col-md-8 v-col-12">
              <div class="d-flex align-center">
                <div>
                  <h3 class="card-title mb-1">รายงานยอดเซ็นสัญญาแบ่งตามไตรมาส ประจำปี {{ selectedYear }}</h3>
                  <h5 class="card-subtitle" style="text-align: left;">(หน่วย : ล้านบาท)</h5>
                </div>
              </div>
            </div>

            <div class="v-col-md-4 v-col-12 text-right">
              <div class="d-flex  justify-end v-col-md-12 v-col-lg-12 v-col-12 ">
                <v-select v-model="selectedYear" label="เลือกปี" :items="yearOptions" class="mr-4"></v-select>
              
              </div>
            </div>
          </div>



          <div class="mt-5">
            <!-- <apexchart type="bar" height="320" :options="chartOptions" :series="chartSeries"></apexchart> -->
            <apexchart  height="350" :options="chartOptions" :series="chartSeries"></apexchart>
          </div>
        </v-card-text>
      </VCard>
    </v-col>

    <v-col cols="12">
      <VCard elevation="10">
        <v-card-text>
            <div class="v-row">
            <div class="v-col-md-6 v-col-12">
              <div class="d-flex align-center">
                <div>
                  <h3 class="card-title mb-1">ตารางยอดเซ็นสัญญาแบ่งตามไตรมาส ประจำปี {{ selectedYear }}</h3>
                  <h5 class="card-subtitle" style="text-align: left;">(หน่วย : ล้านบาท)</h5>
                </div>
              </div>
            </div>

            <div class="v-col-md-6 v-col-12 text-right">
              <div class="d-flex  justify-end v-col-md-12 v-col-lg-12 v-col-12 ">
               
                <v-btn class=" text-primary   v-btn--size-large " @click="exportToExcel">
                  <div class="text-none font-weight-regular muted">Export to CSV</div>
                </v-btn>
              </div>
            </div>
          </div>
          <div class="v-table v-theme--BLUE_THEME v-table--density-default month-table">
            <div class="v-table__wrapper">
              <table>
                <thead style="background-color: #F5F5F5;">
                  <tr>
                    <th class="text-h6">Financial Summary</th>
                    <th class="text-h6" :colspan="visibleQuarters.length + 1"
                      style="text-align: center; border-bottom: 2px solid #00A6D4;">{{ selectedYear }}</th>

                  </tr>
                  <tr>
                    <th class="text-subtitle-1" style="font-weight: 400;">(Unit: THB million)</th>
                    <th v-for="q in visibleQuarters" :key="q" class="text-h6">{{ q }}</th>
                    <th class="text-h6">{{ selectedYear }}</th>

                  </tr>
                </thead>
                <tbody>
                  <template v-for="label in priceRanges" :key="label">
                    <tr style="background-color: #fcf8ff;">
                      <td :colspan="2">
                        <h6 class="text-p" style="font-size: 14px; font-weight: 600; color: #725AF2;">{{ label }}</h6>
                      </td>

                      <td v-for="q in visibleQuarters" :key="label + '-' + q"></td>
                    </tr>
                    <tr v-for="type in dataTypes" :key="label + '-' + type">
                      <td>
                        <h6 class="text-subtitle-1">{{ type }}</h6>
                      </td>
                      <td v-for="q in visibleQuarters" :key="type + '-' + label + '-' + q">
                        <h6 class="text-subtitle-1">
                          {{ formatNumber(summaryData[q.split(' ')[0]]?.[label]?.[typeMap[type]] || 0) }}
                        </h6>
                      </td>
                      <td style="background-color: #FFF3E0;">
                        {{ getRowTotal(label, typeMap[type]) }}
                      </td>


                    </tr>
                  </template>

                  
                  <!-- <tr style="background-color: #fcf8ff;">
                    <td>
                      <h6 class="text-p" style="font-size: 13px; font-weight: 600; color: #F8285A;">จำนวนหลัง (รวม)</h6>
                    </td>
                    <td v-for="q in visibleQuarters" :key="'unit-total-' + q">
                      <h6 class="text-p" style="font-size: 14px; font-weight: 600; color: #F8285A;">
                        {{ getQuarterTotal(q, 'unit') }}
                      </h6>
                    </td>
                    <td style="background-color: #FFF3E0;">
                      <h6 class="text-p" style="font-size: 14px; font-weight: 600; color: #F8285A;">
                        {{ getTotalYearTotal('unit') }}
                      </h6>
                    </td>
                  </tr>

                  <tr style="background-color: #fcf8ff;">
                    <td>
                      <h6 class="text-p" style="font-size: 13px; font-weight: 600; color: #F8285A;">มูลค่ารวม (รวม)</h6>
                    </td>
                    <td v-for="q in visibleQuarters" :key="'value-total-' + q">
                      <h6 class="text-p" style="font-size: 14px; font-weight: 600; color: #F8285A;">
                        {{ getQuarterTotal(q, 'value') }}
                      </h6>
                    </td>
                    <td style="background-color: #FFF3E0;">
                      <h6 class="text-p" style="font-size: 14px; font-weight: 600; color: #F8285A;">
                        {{ getTotalYearTotal('value') }}
                      </h6>
                    </td>
                  </tr>

                  <tr style="background-color: #fcf8ff;">
                    <td>
                      <h6 class="text-p" style="font-size: 13px; font-weight: 600; color: #F8285A;">พื้นที่ใช้สอย (รวม)
                      </h6>
                    </td>
                    <td v-for="q in visibleQuarters" :key="'area-total-' + q">
                      <h6 class="text-p" style="font-size: 14px; font-weight: 600; color: #F8285A;">
                        {{ getQuarterTotal(q, 'area') }}
                      </h6>
                    </td>
                    <td style="background-color: #FFF3E0;">
                      <h6 class="text-p" style="font-size: 14px; font-weight: 600; color: #F8285A;">
                        {{ getTotalYearTotal('area') }}
                      </h6>
                    </td>
                  </tr> -->
                </tbody>
              </table>
            </div>
          </div>
        </v-card-text>
      </VCard>
    </v-col>
  </v-row>
</template>
<style></style>
