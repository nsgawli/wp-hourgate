import React, { useState, useEffect } from 'react';
import {
	IoPlaySkipBackOutline,
	IoPlaySkipForwardOutline,
	IoChevronBackOutline,
	IoChevronForwardOutline,
} from 'react-icons/io5';

import { Flex, Button } from '@framework/components';

import './index.css';

/**
 * Builds the list of page numbers to render, collapsing long ranges
 * into ellipsis markers (e.g. 1 … 4 5 [6] 7 8 … 20).
 *
 * @param {number} current      Current active page.
 * @param {number} total        Total number of pages.
 * @param {number} siblingCount Number of pages to show on each side of current.
 * @return {Array} List of page numbers and ellipsis markers.
 */
const getPageNumbers = (current, total, siblingCount = 1) => {
	const pages = [];

	for (let page = 1; page <= total; page++) {
		const isFirstOrLast = page === 1 || page === total;
		const isWithinSiblings =
			page >= current - siblingCount && page <= current + siblingCount;

		if (isFirstOrLast || isWithinSiblings) {
			pages.push(page);
		}
	}

	const withEllipsis = [];
	let previousPage = null;

	pages.forEach((page) => {
		if (previousPage !== null && page - previousPage > 1) {
			withEllipsis.push(`ellipsis-${previousPage}`);
		}
		withEllipsis.push(page);
		previousPage = page;
	});

	return withEllipsis;
};

const Pagination = ({
	current = 1,
	total = 1,
	totalItems = 0,
	perPage = 20,
	label = 'Logs',
	onChange = null,
}) => {
	const [jumpValue, setJumpValue] = useState('');

	useEffect(() => {
		setJumpValue('');
	}, [current]);

	const startItem = totalItems === 0 ? 0 : (current - 1) * perPage + 1;

	const endItem = Math.min(current * perPage, totalItems);

	const goToPage = (page) => {
		const targetPage = Math.min(Math.max(page, 1), total);

		if (targetPage !== current && onChange) {
			onChange(targetPage);
		}
	};

	const handleJumpSubmit = () => {
		const page = parseInt(jumpValue, 10);

		if (!isNaN(page)) {
			goToPage(page);
		}

		setJumpValue('');
	};

	const pageNumbers = getPageNumbers(current, total);

	return (
		<Flex
			className="hourgate-pagination"
			justify="space-between"
			align="center"
			gap={10}
			wrap
		>
			<div className="hourgate-pagination-info">
				{startItem} - {endItem} of {totalItems} {label}
			</div>

			<Flex
				className="hourgate-pagination-controls"
				align="center"
				gap={4}
				wrap
			>
				<Button
					size="small"
					disabled={current === 1}
					onClick={() => goToPage(1)}
					icon={<IoPlaySkipBackOutline />}
				/>

				<Button
					size="small"
					disabled={current === 1}
					onClick={() => goToPage(current - 1)}
					icon={<IoChevronBackOutline />}
				/>

				{pageNumbers.map((page) => {
					if (typeof page !== 'number') {
						return (
							<span
								key={page}
								className="hourgate-pagination-ellipsis"
							>
								&hellip;
							</span>
						);
					}

					return (
						<Button
							key={page}
							size="small"
							type={page === current ? 'primary' : 'default'}
							className="hourgate-pagination-page"
							onClick={() => goToPage(page)}
						>
							{page}
						</Button>
					);
				})}

				<Button
					size="small"
					disabled={current === total}
					onClick={() => goToPage(current + 1)}
					icon={<IoChevronForwardOutline />}
				/>

				<Button
					size="small"
					disabled={current === total}
					onClick={() => goToPage(total)}
					icon={<IoPlaySkipForwardOutline />}
				/>
			</Flex>

			{total > 1 && (
				<Flex className="hourgate-pagination-jump" align="center" gap={6}>
					<label htmlFor="hourgate-pagination-jump-input">
						Go to page
					</label>

					<input
						id="hourgate-pagination-jump-input"
						type="number"
						min={1}
						max={total}
						placeholder={String(current)}
						value={jumpValue}
						onChange={(event) => {
							setJumpValue(event.target.value);
						}}
						onKeyDown={(event) => {
							if (event.key === 'Enter') {
								handleJumpSubmit();
							}
						}}
					/>

					<Button size="small" onClick={handleJumpSubmit}>
						Go
					</Button>
				</Flex>
			)}
		</Flex>
	);
};

export default Pagination;
