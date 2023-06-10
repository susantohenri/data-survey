jQuery.get(data_survey.url, function (response) {
    if (response) {
        var resp_json = JSON.parse(response)
        var data_survey_chosen_field = resp_json[0]
        var data_survey_chosen_display_type = resp_json[2].trim()
        var data_survey_form = jQuery(`[id="form_5datasurvey2"]`)

        jQuery(`[name="item_meta[3890]"]`).val(data_survey_chosen_field).trigger('change')
        jQuery(`[name="item_meta[3891]"]`).val(data_survey_chosen_display_type)
    
        data_survey_form.find(`[type="checkbox"], [type="radio"]`).click(function () {
            data_survey_form.submit()
        })
    }
})