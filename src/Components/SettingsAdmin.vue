<script setup>
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { ref, watch } from 'vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

function save(setting) {
	const value = settings[setting]

	axios.put(generateUrl('/apps/churchtools_integration/settings/set'), {
		setting,
		value: value.value,
	}).then(function(response) {
		if (response.data.value ?? null) {
			value.value = response.data.value
		}

		// input.success = true
	})
}

function watcher(setting, ref) {
	watch(ref, () => save(setting))
}

function createWatchedRef(setting, fallback) {
	const r = ref(loadState('churchtools_integration', setting, fallback ?? null))
	watcher(setting, r)
	return r
}

function createRef(setting, fallback) {
	return ref(loadState('churchtools_integration', setting, fallback ?? null))
}

const apiState = ref(false)

async function checkApi() {
	if (apiState.value === 'fetching') { return }

	apiState.value = 'fetching'
	await new Promise(resolve => setTimeout(resolve, 5000)) // wait 5s
	axios.post(generateUrl('/apps/churchtools_integration/settings/check_api'))
		.then((response) => {
			apiState.value = response.data
		})
		.catch(() => {
			apiState.value = false
		})
}

checkApi()

const settings = {
	url: createRef('url'),
	user_prefix: createRef('user_prefix'),
	group_prefix: createRef('group_prefix'),
	oauth2_enabled: createWatchedRef('oauth2_enabled', false),
	oauth2_hide_default: createWatchedRef('oauth2_hide_default', false),
	oauth2_use_username: createWatchedRef('oauth2_use_username', false),
	oauth2_sync_groups: createWatchedRef('oauth2_sync_groups', false),
	oauth2_sync_role_groups: createWatchedRef('oauth2_sync_role_groups', false),
	oauth2_create_groups: createWatchedRef('oauth2_create_groups', false),
	oauth2_redirect_uri: createRef('oauth2_redirect_uri'),
	oauth2_client_id: ref(''),
	oauth2_login_label: createRef('oauth2_login_label'),
	api_enabled: createWatchedRef('api_enabled', false),
	api_token: ref(''),
	groupfolders_enabled: createWatchedRef('groupfolders_enabled', false),
	groupfolders_tag: createRef('groupfolders_tag'),
	groupfolders_leader_group_suffix: createRef('groupfolders_leader_group_suffix'),
}

const jobRunning = ref(false)
const jobReturn = ref(null)

function runJob() {
	jobRunning.value = true
	jobReturn.value = null
	axios.post(generateUrl('/apps/churchtools_integration/settings/run-job')).then((response) => {
		jobReturn.value = response.data.message
		jobRunning.value = false
	})
}

</script>

<template>
	<form>
		<fieldset class="section">
			<h2>{{ t('churchtools_integration', 'General') }}</h2>

			<NcInputField v-model="settings.url.value"
				:label="t('churchtools_integration', 'ChurchTools URL')"
				@change="save('url'); checkApi()" />

			<NcNoteCard v-if="apiState === false" type="warning" text="API Status unknown!" />
			<NcNoteCard v-if="apiState === 'fetching'" type="info" text="API Status is being fetched ..." />
			<div v-else>
				<NcNoteCard v-if="apiState.info ?? null" type="success">
					Connected to {{ apiState.info.shortName }} @ {{ apiState.url }}
				</NcNoteCard>
				<NcNoteCard v-else type="error">
					Failed to connect to {{ apiState.url }}: {{ apiState.error ?? 'Unkown error!' }}
				</NcNoteCard>
			</div>
			<NcButton :disabled="apiState === 'fetching'" @click="checkApi()">
				Check API Connection
			</NcButton>

			<NcInputField v-model="settings.user_prefix.value"
				:label="t('churchtools_integration', 'User Prefix')"
				@change="save('user_prefix'); checkApi()" />
			<NcInputField v-model="settings.group_prefix.value"
				:label="t('churchtools_integration', 'Group Prefix')"
				@change="save('group_prefix'); checkApi()" />
		</fieldset>
		<fieldset class="section">
			<h2>{{ t('churchtools_integration', 'OAuth2 Login') }}</h2>

			<NcCheckboxRadioSwitch type="switch"
				:checked.sync="settings.oauth2_enabled.value">
				{{ t('settings', 'Enable') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch type="switch"
				:checked.sync="settings.oauth2_hide_default.value">
				{{ t('settings', 'Hide Default Login') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch type="switch"
				:checked.sync="settings.oauth2_use_username.value">
				{{ t('settings', 'Use ChurchTools Username') }}
			</NcCheckboxRadioSwitch>

			<NcNoteCard v-if="settings.api_enabled.value" type="info">
				You may disable all group sync options here if you've configured group sync through the API User. It is recommended to use the API User settings for syncing groups instead of OAuth2.
			</NcNoteCard>
			<NcCheckboxRadioSwitch type="switch"
				:checked.sync="settings.oauth2_sync_groups.value">
				{{ t('settings', 'Sync Groups') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch type="switch"
				:checked.sync="settings.oauth2_sync_role_groups.value">
				{{ t('settings', 'Sync Role Groups') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch type="switch"
				:checked.sync="settings.oauth2_create_groups.value">
				{{ t('settings', 'Create Groups (if any group sync is enabled)') }}
			</NcCheckboxRadioSwitch>

			<NcInputField v-model="settings.oauth2_client_id.value"
				:label="t('churchtools_integration', 'Client Identifier')"
				@change="save('oauth2_client_id')" />
			<NcInputField v-model="settings.oauth2_login_label.value"
				:label="t('churchtools_integration', 'Login Label')"
				@change="save('oauth2_login_label')" />
			<NcInputField v-model="settings.oauth2_redirect_uri.value"
				:label="t('churchtools_integration', 'Redirect URI')"
				:disabled="true" />
		</fieldset>
		<fieldset class="section">
			<h2>{{ t('churchtools_integration', 'API User') }}</h2>

			<NcCheckboxRadioSwitch type="switch"
				:checked.sync="settings.api_enabled.value">
				{{ t('settings', 'Enable') }}
			</NcCheckboxRadioSwitch>
			<NcInputField v-model="settings.api_token.value"
				:label="t('churchtools_integration', 'API Token')"
				@change="save('api_token')" />

			<div v-if="settings.api_enabled.value">
				<NcNoteCard v-if="apiState === false" type="warning" text="API Status unknown!" />
				<NcNoteCard v-if="apiState === 'fetching'" type="info" text="API Status is being fetched ..." />
				<div v-else>
					<NcNoteCard v-if="apiState.user_id ?? null" type="success">
						API connected to user #{{ apiState.user_id }}
					</NcNoteCard>
					<NcNoteCard v-else type="error">
						API not connected to any user!
					</NcNoteCard>
				</div>
				<NcButton :disabled="apiState === 'fetching'" @click="checkApi()">
					Check API Connection
				</NcButton>
			</div>
		</fieldset>
		<fieldset class="section">
			<h2>{{ t('churchtools_integration', 'Groupfolders') }}</h2>
			<NcNoteCard type="info">
				Make sure the <a href="https://github.com/nextcloud/groupfolders/">Groupfolders app</a> is active and the API User is configured if you're using this option.
			</NcNoteCard>

			<NcCheckboxRadioSwitch type="switch"
				:checked.sync="settings.groupfolders_enabled.value">
				{{ t('settings', 'Enable') }}
			</NcCheckboxRadioSwitch>
			<NcInputField v-model="settings.groupfolders_tag.value"
				:label="t('churchtools_integration', 'Groupfolder Tag')"
				@change="save('groupfolders_tag')" />
			<NcInputField v-model="settings.groupfolders_leader_group_suffix.value"
				:label="t('churchtools_integration', 'Leaders Group Suffix')"
				@change="save('groupfolders_leader_group_suffix')" />
		</fieldset>
		<fieldset class="section">
			<p>
				<input type="button" value="Run Job" @click="runJob">
				<span v-if="jobRunning">Running ...</span>
				<span v-if="jobReturn">{{ jobReturn }}</span>
			</p>
		</fieldset>
	</form>
</template>

<style scoped lang="scss">
.input-field {
	margin-block-start: 12px;
}
</style>
