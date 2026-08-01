<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  GAMBIT_PRESET_COUNT,
  CONDITION_OPTIONS,
  ACTION_OPTIONS,
  findConditionOption,
  getMercenaries,
  getGambitCatalog,
  getGambits,
  updateGambitPreset,
  setActiveGambitPreset,
  type Mercenary,
  type GambitCatalogData,
  type GambitPresetsData,
  type GambitRule,
  type GambitActionType,
  type SkillInfo,
} from '@/lib/mercenaryApi'
import { portraitStyle } from '@/lib/portrait'
import strings from '@/locales/ko'

interface EditableRule {
  conditionOptionId: string
  value: number
  debuffKey: string
  actionType: GambitActionType
  skillKey: string
  itemKey: string
}

const presetNumbers = Array.from({ length: GAMBIT_PRESET_COUNT }, (_, i) => i + 1)

const roster = ref<Mercenary[]>([])
const selectedUnit = ref<Mercenary | null>(null)
const catalog = ref<GambitCatalogData>({ skills: [], items: [], debuffs: [] })
const gambitData = ref<GambitPresetsData | null>(null)
const viewingPreset = ref(1)
const rules = ref<EditableRule[]>([])
const dirty = ref(false)
const loading = ref(true)
const saving = ref(false)
const message = ref('')

function isAlwaysAttack(rule: GambitRule): boolean {
  return rule.condition_kind === 'always' && rule.action_type === 'attack'
}

function ruleToEditable(rule: GambitRule): EditableRule {
  const option = findConditionOption(rule)
  return {
    conditionOptionId: option.id,
    value: rule.condition_value ?? 1,
    debuffKey: rule.condition_debuff_key ?? catalog.value.debuffs[0]?.key ?? '',
    actionType: rule.action_type,
    skillKey: rule.action_type === 'skill' ? (rule.action_skill_key ?? catalog.value.skills[0]?.key ?? '') : (catalog.value.skills[0]?.key ?? ''),
    itemKey: rule.action_type === 'item' ? (rule.action_skill_key ?? catalog.value.items[0]?.key ?? '') : (catalog.value.items[0]?.key ?? ''),
  }
}

function defaultEditableRule(): EditableRule {
  return {
    conditionOptionId: 'always',
    value: 50,
    debuffKey: catalog.value.debuffs[0]?.key ?? '',
    actionType: 'attack',
    skillKey: catalog.value.skills[0]?.key ?? '',
    itemKey: catalog.value.items[0]?.key ?? '',
  }
}

function editableToRule(e: EditableRule): GambitRule {
  const option = CONDITION_OPTIONS.find((o) => o.id === e.conditionOptionId) ?? CONDITION_OPTIONS[0]!
  return {
    condition_kind: option.condition_kind,
    condition_scope: option.condition_scope,
    condition_stat: option.condition_stat,
    condition_value_type: option.condition_value_type,
    condition_operator: option.condition_operator,
    condition_value: option.needsValue ? e.value : null,
    condition_debuff_key: option.needsDebuff ? e.debuffKey : null,
    condition_position: option.condition_position,
    action_type: e.actionType,
    action_skill_key: e.actionType === 'skill' ? e.skillKey : e.actionType === 'item' ? e.itemKey : null,
  }
}

async function loadRoster() {
  const all = await getMercenaries()
  roster.value = all.filter((u) => u.owned)
}

function loadPresetIntoEditor(presetNumber: number) {
  const list = gambitData.value?.presets[String(presetNumber)] ?? []
  const editableList = [...list]
  const last = editableList[editableList.length - 1]
  if (last && isAlwaysAttack(last)) {
    editableList.pop()
  }
  rules.value = editableList.map(ruleToEditable)
  dirty.value = false
}

function confirmLeavingIfDirty(): boolean {
  if (!dirty.value) return true
  return window.confirm(strings.mercenary.gambit.confirmLeave)
}

async function selectUnit(unit: Mercenary) {
  if (selectedUnit.value?.id === unit.id) return
  if (!confirmLeavingIfDirty()) return

  selectedUnit.value = unit
  loading.value = true
  message.value = ''
  try {
    ;[catalog.value, gambitData.value] = await Promise.all([getGambitCatalog(unit.id), getGambits(unit.id)])
    viewingPreset.value = gambitData.value.active_preset
    loadPresetIntoEditor(viewingPreset.value)
  } catch {
    message.value = strings.mercenary.gambit.loadFailed
  } finally {
    loading.value = false
  }
}

function switchPreset(presetNumber: number) {
  if (presetNumber === viewingPreset.value) return
  if (!confirmLeavingIfDirty()) return
  viewingPreset.value = presetNumber
  loadPresetIntoEditor(presetNumber)
}

function addRule() {
  rules.value.push(defaultEditableRule())
  dirty.value = true
}

function removeRule(index: number) {
  rules.value.splice(index, 1)
  dirty.value = true
}

function moveRule(index: number, direction: -1 | 1) {
  const target = index + direction
  if (target < 0 || target >= rules.value.length) return
  const item = rules.value.splice(index, 1)[0]!
  rules.value.splice(target, 0, item)
  dirty.value = true
}

function conditionOptionFor(id: string) {
  return CONDITION_OPTIONS.find((o) => o.id === id) ?? CONDITION_OPTIONS[0]!
}

function targetSideLabel(side: SkillInfo['target_side']): string {
  const g = strings.mercenary.gambit
  return side === 'ally' ? g.ally : side === 'self' ? g.self : g.enemy
}

async function save() {
  if (!selectedUnit.value) return
  saving.value = true
  message.value = ''
  try {
    const editedRules = rules.value.map(editableToRule)
    const pinned: GambitRule = {
      condition_kind: 'always',
      condition_scope: null,
      condition_stat: null,
      condition_value_type: null,
      condition_operator: null,
      condition_value: null,
      condition_debuff_key: null,
      condition_position: null,
      action_type: 'attack',
      action_skill_key: null,
    }
    await updateGambitPreset(selectedUnit.value.id, viewingPreset.value, [...editedRules, pinned])
    gambitData.value = await getGambits(selectedUnit.value.id)
    loadPresetIntoEditor(viewingPreset.value)
  } catch (e) {
    message.value = e instanceof Error ? e.message : strings.mercenary.gambit.saveFailed
  } finally {
    saving.value = false
  }
}

async function useThisPreset() {
  if (!selectedUnit.value) return
  try {
    await setActiveGambitPreset(selectedUnit.value.id, viewingPreset.value)
    if (gambitData.value) gambitData.value.active_preset = viewingPreset.value
  } catch (e) {
    message.value = e instanceof Error ? e.message : strings.mercenary.gambit.applyFailed
  }
}

onMounted(async () => {
  try {
    await loadRoster()
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="gambit-tab">
    <div class="roster">
      <div
        v-for="unit in roster"
        :key="unit.id"
        class="roster-card"
        :class="{ selected: selectedUnit?.id === unit.id }"
        @click="selectUnit(unit)"
      >
        <div class="portrait" :style="portraitStyle(unit.sprite, 48)"></div>
        <span>{{ unit.name }}</span>
      </div>
      <p v-if="roster.length === 0 && !loading" class="hint">{{ strings.mercenary.gambit.noRoster }}</p>
    </div>

    <p v-if="message" class="message">{{ message }}</p>

    <template v-if="selectedUnit">
      <div class="preset-bar">
        <button
          v-for="n in presetNumbers"
          :key="n"
          class="preset-tab"
          :class="{ active: viewingPreset === n, viewing: gambitData?.active_preset === n }"
          @click="switchPreset(n)"
        >
          {{ strings.mercenary.gambit.preset(n) }}
        </button>
      </div>

      <div class="actions">
        <button class="use-button" :disabled="gambitData?.active_preset === viewingPreset" @click="useThisPreset">
          {{ gambitData?.active_preset === viewingPreset ? strings.mercenary.gambit.usingPreset : strings.mercenary.gambit.usePreset }}
        </button>
        <button class="add-button" @click="addRule">{{ strings.mercenary.gambit.addRule }}</button>
        <button class="save-button" :disabled="!dirty || saving" @click="save">
          {{ saving ? strings.mercenary.gambit.saving : strings.mercenary.gambit.save }}
        </button>
      </div>

      <div class="rule-list">
        <div v-for="(rule, index) in rules" :key="index" class="rule-row">
          <span class="priority">{{ index + 1 }}</span>

          <select v-model="rule.conditionOptionId" class="select" @change="dirty = true">
            <option v-for="opt in CONDITION_OPTIONS" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
          </select>

          <input
            v-if="conditionOptionFor(rule.conditionOptionId).needsValue"
            v-model.number="rule.value"
            type="number"
            class="value-input"
            min="1"
            :max="conditionOptionFor(rule.conditionOptionId).condition_value_type === 'percent' ? 100 : 9999"
            @change="dirty = true"
          />

          <select
            v-if="conditionOptionFor(rule.conditionOptionId).needsDebuff"
            v-model="rule.debuffKey"
            class="select"
            @change="dirty = true"
          >
            <option v-for="d in catalog.debuffs" :key="d.key" :value="d.key">{{ d.label }}</option>
          </select>

          <select v-model="rule.actionType" class="select" @change="dirty = true">
            <option v-for="opt in ACTION_OPTIONS" :key="opt.type" :value="opt.type">{{ opt.label }}</option>
          </select>

          <select v-if="rule.actionType === 'skill'" v-model="rule.skillKey" class="select" @change="dirty = true">
            <option v-for="s in catalog.skills" :key="s.key" :value="s.key">
              {{ s.name }} ({{ strings.mercenary.gambit.skillTarget(targetSideLabel(s.target_side)) }})
            </option>
          </select>

          <select v-if="rule.actionType === 'item'" v-model="rule.itemKey" class="select" @change="dirty = true">
            <option v-for="i in catalog.items" :key="i.key" :value="i.key">
              {{ i.name }} ({{ strings.mercenary.gambit.skillTarget(targetSideLabel(i.target_side)) }})
            </option>
          </select>

          <div class="row-buttons">
            <button :disabled="index === 0" @click="moveRule(index, -1)">{{ strings.mercenary.gambit.moveUp }}</button>
            <button :disabled="index === rules.length - 1" @click="moveRule(index, 1)">{{ strings.mercenary.gambit.moveDown }}</button>
            <button class="danger" @click="removeRule(index)">{{ strings.mercenary.gambit.delete }}</button>
          </div>
        </div>

        <div class="rule-row pinned">
          <span class="priority">{{ rules.length + 1 }}</span>
          <span class="pinned-label">{{ strings.mercenary.gambit.pinnedRule }}</span>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.hint {
  opacity: 0.7;
  font-size: 0.9rem;
}

.message {
  color: #f87171;
  font-size: 0.9rem;
  margin-bottom: 0.75rem;
}

.roster {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  margin-bottom: 1.25rem;
}

.roster-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.3rem;
  padding: 0.5rem;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.78rem;
  background: rgba(255, 255, 255, 0.03);
}

.roster-card.selected {
  border-color: hsla(160, 100%, 37%, 1);
  background: rgba(16, 185, 129, 0.15);
}

.portrait {
  border-radius: 6px;
  background-color: rgba(0, 0, 0, 0.3);
}

.preset-bar {
  display: flex;
  gap: 0.4rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.preset-tab {
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: #f1f5f9;
  border-radius: 6px;
  padding: 0.4rem 0.8rem;
  cursor: pointer;
  font-family: inherit;
  font-size: 0.82rem;
}

.preset-tab.active {
  background: rgba(255, 255, 255, 0.18);
}

.preset-tab.viewing {
  border-color: hsla(160, 100%, 37%, 1);
}

.actions {
  display: flex;
  gap: 0.6rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.use-button,
.add-button,
.save-button {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  font-family: inherit;
  font-size: 0.85rem;
  cursor: pointer;
  background: rgba(255, 255, 255, 0.1);
  color: #f1f5f9;
}

.use-button:disabled {
  opacity: 0.5;
  cursor: default;
}

.save-button {
  background: hsla(160, 100%, 37%, 1);
  color: #fff;
}

.save-button:disabled {
  opacity: 0.4;
  cursor: default;
}

.rule-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.rule-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  padding: 0.5rem;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.03);
}

.rule-row.pinned {
  opacity: 0.6;
}

.priority {
  font-size: 0.75rem;
  opacity: 0.6;
  min-width: 1.2rem;
}

.pinned-label {
  font-size: 0.8rem;
}

.select,
.value-input {
  background: #1e293b;
  color: #f1f5f9;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 6px;
  padding: 0.35rem 0.5rem;
  font-family: inherit;
  font-size: 0.8rem;
}

.value-input {
  width: 5rem;
}

.row-buttons {
  display: flex;
  gap: 0.3rem;
  margin-left: auto;
}

.row-buttons button {
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: #f1f5f9;
  border-radius: 6px;
  padding: 0.3rem 0.5rem;
  cursor: pointer;
  font-size: 0.75rem;
}

.row-buttons button:disabled {
  opacity: 0.3;
  cursor: default;
}

.row-buttons button.danger {
  color: #f87171;
}
</style>
