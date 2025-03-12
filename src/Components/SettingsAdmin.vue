<script setup>
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { ref } from 'vue'

const url = ref(loadState('churchtools_integration', 'url'))
const userToken = ref()
const socialLoginName = ref(loadState('churchtools_integration', 'sociallogin_name'))

const saving = ref(false)
const jobRunning = ref(false)
const jobReturn = ref(null)

function submit() {
	saving.value = true
	axios.put(generateUrl('/apps/churchtools_integration/settings/admin'), {
		url: url.value,
		userToken: userToken.value,
		socialLoginName: socialLoginName.value,
	}).then(() => { saving.value = false })
}

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
	<div class="section">
		<p>
			<label for="churchtools_integration_url">{{ t('churchtools_integration', 'URL') }}</label>
			<input v-model="url" type="text" name="churchtools_integration_url">
		</p>
		<p>
			<label for="churchtools_integration_user_token">{{ t('churchtools_integration', 'User Token') }}</label>
			<input v-model="userToken" type="text" name="churchtools_integration_user_token">
		</p>
		<p>
			<label for="churchtools_integration_sociallogin_name">{{ t('churchtools_integration', 'SocialLogin Internal Name') }}</label>
			<input v-model="socialLoginName" type="text" name="churchtools_integration_sociallogin_name">
		</p>
		<p>
			<input type="button" value="Save" @click="submit">
			<span v-if="saving">Saving ...</span>
		</p>
		<p>
			<input type="button" value="Run Job" @click="runJob">
			<span v-if="jobRunning">Running ...</span>
			<span v-if="jobReturn">{{ jobReturn }}</span>
		</p>
	</div>
</template>

<style scoped lang="scss">
label {
	display: inline-block;
	min-width: 200px;
}
</style>
