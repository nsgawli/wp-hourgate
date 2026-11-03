import React from 'react';
import { IoSearchOutline } from 'react-icons/io5';
import './index.css';

const SearchInput = ({
	value = '',
	onChange = null,
	placeholder = 'Search...',
}) => {
	return (
		<div className="hourgate-search-input">
			<IoSearchOutline />

			<input
				type="text"
				value={value}
				placeholder={placeholder}
				onChange={(event) => {
					onChange(event.target.value);
				}}
			/>
		</div>
	);
};

export default SearchInput;