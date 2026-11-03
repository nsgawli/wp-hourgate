import React from 'react';
import { __ } from '@wordpress/i18n';
import { Controller, useController } from 'react-hook-form';

// datepicker
import DatePicker, { registerLocale } from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import { formatTime, customLocale } from '@framework/utils/formatdate';
registerLocale('custom-en', customLocale);

import { Flex } from '@framework/components';
import { Label } from '@framework/components/form';
import { useFieldIds, fieldAria } from '@framework/components/form/fieldIds';
import '../../index.css'; // form styles
import '../index.css'; // date styles

/**
 * Legacy react-datepicker time picker, inherited from the original framework and unused.
 * Prefer `TimeField` for schedule times: this one steps in 30 minutes, can't
 * show `24:00` or "until midnight", and parses `new Date( '2024-01-01 09:00:00' )`,
 * a non-ISO string browsers may read differently. Kept unchanged so nothing
 * that might import it breaks (OW-020).
 */
const Time = (props) => {
	const {
		name,
		control,
		label = null,
		validations = {},
		className = '',
		extaInfo = '',
		style = {},
	} = props;

	const { fieldState } = useController({
		name,
		control,
	});
	const ids = useFieldIds();
	const aria = fieldAria(ids, {
		info: extaInfo,
		error: fieldState.error?.message,
		required: !!validations.required,
	});

	return (
		<Flex
			className={
				className ? `hourgate-form-field ${className}` : 'hourgate-form-field'
			}
			style={style}
			vertical
			gap={3}
		>
			{label && (
				<Label
					text={label}
					htmlFor={ids.field}
					required={validations.required ? true : false}
				/>
			)}
			{extaInfo && <small id={ids.info}>{extaInfo}</small>}
			<Controller
				name={name}
				control={control}
				rules={validations}
				render={({ field }) => (
					<DatePicker
						id={ids.field}
						ariaDescribedBy={aria['aria-describedby']}
						ariaInvalid={aria['aria-invalid'] ? 'true' : undefined}
						ariaRequired={aria['aria-required'] ? 'true' : undefined}
						showTimeSelect
						showTimeSelectOnly
						timeIntervals={props.timeIntervals || 30}
						selected={
							field.value
								? new Date(`2024-01-01 ${field.value}:00`)
								: new Date()
						}
						onChange={(date) => {
							field.onChange(formatTime(date));
						}}
						timeCaption={__('Time', 'hourgate')}
						dateFormat="h:mm aa"
						minTime={
							props.minTime
								? new Date(`2024-01-01T${props.minTime}:00`)
								: null
						}
						maxTime={
							props.maxTime
								? new Date(`2024-01-01T${props.maxTime}:00`)
								: null
						}
						locale="custom-en"
					/>
				)}
			/>
			{fieldState.error && fieldState.error.message && (
				<small id={ids.error} className="hourgate-form-field-error-msg">
					{fieldState.error.message}
				</small>
			)}
		</Flex>
	);
};

export default Time;
