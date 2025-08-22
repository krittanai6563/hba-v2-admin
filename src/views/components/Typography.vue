<script setup lang="ts">
import UiParentCard from '@/components/shared/UiParentCard.vue';

const now = new Date()
const currentMonthNumber = now.getMonth() + 1
const currentYear = now.getFullYear() + 543
const monthNameTH = now.toLocaleString('th-TH', { month: 'long' })
const currentMonthName = `${monthNameTH} ${currentYear}`
const currentQuarter = Math.floor((currentMonthNumber - 1) / 3) + 1


</script>

<template>
    <v-row>
         <v-col cols="12" sm="12" lg="12">

            <div class="mt-3 mb-6">
                
                <div class="d-flex justify-space-between">
                    <div class="d-flex py-0 align-center">
                        <div>
                            <h3 class="text-h5 card-title">บันทึกข้อมูลยอดเซ็นสัญญา</h3>
                            <ul
                                class="v-breadcrumbs v-breadcrumbs--density-default text-subtitle-1 textSecondary pa-0 ml-n1">
                              
                                <li class="v-breadcrumbs-item" text="Home"><a class="v-breadcrumbs-item--link" href="#">
                                        <p>หน้าแรก</p>
                                    </a></li>
                                <li class="v-breadcrumbs-divider"><i
                                        class="mdi-chevron-right mdi v-icon notranslate v-theme--BLUE_THEME"
                                        aria-hidden="true" style="font-size: 15px; height: 15px; width: 15px;"></i></li>
                                <li class="v-breadcrumbs-item" text="Dashboard"><a class="v-breadcrumbs-item--link"
                                        href="#">
                                        <p>บันทึกข้อมูลยอดเซ็นสัญญา</p>
                                    </a></li>
                            </ul>
                        </div>
                    </div>

                    <div>
                        
                    </div>
                </div>
            </div>

        </v-col>
        <v-col cols="12" sm="12" lg="12">
            
           <UiParentCard :monthName="currentMonthName" :quarter="currentQuarter" />

                 
           
           
        </v-col>
    </v-row>
</template>
