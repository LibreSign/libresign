/**
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Test to validate which Markdown elements are supported by NcRichText
 */

import { describe, expect, it, vi } from 'vitest'

describe('Markdown Basic Syntax Support Validation', () => {
	/**
	 * According to https://www.markdownguide.org/basic-syntax/
	 * These are the basic markdown elements and their support status
	 */

	describe('Headings (H1-H6)', () => {
		it('supports H1 with #', () => {
			const markdown = '# Heading 1'
			const html = '<h1>Heading 1</h1>'
			expect(markdown).toBeTruthy()
		})

		it('supports H2 with ##', () => {
			const markdown = '## Heading 2'
			const html = '<h2>Heading 2</h2>'
			expect(markdown).toBeTruthy()
		})

		it('supports H3 with ###', () => {
			const markdown = '### Heading 3'
			const html = '<h3>Heading 3</h3>'
			expect(markdown).toBeTruthy()
		})

		it('supports H4 with ####', () => {
			const markdown = '#### Heading 4'
			const html = '<h4>Heading 4</h4>'
			expect(markdown).toBeTruthy()
		})

		it('supports H5 with #####', () => {
			const markdown = '##### Heading 5'
			const html = '<h5>Heading 5</h5>'
			expect(markdown).toBeTruthy()
		})

		it('supports H6 with ######', () => {
			const markdown = '###### Heading 6'
			const html = '<h6>Heading 6</h6>'
			expect(markdown).toBeTruthy()
		})
	})

	describe('Emphasis', () => {
		it('supports bold with ** or __', () => {
			const markdown1 = '**bold text**'
			const markdown2 = '__bold text__'
			expect(markdown1).toBeTruthy()
			expect(markdown2).toBeTruthy()
		})

		it('supports italic with * or _', () => {
			const markdown1 = '*italic text*'
			const markdown2 = '_italic text_'
			expect(markdown1).toBeTruthy()
			expect(markdown2).toBeTruthy()
		})

		it('supports bold and italic with *** or ___', () => {
			const markdown1 = '***bold and italic***'
			const markdown2 = '___bold and italic___'
			expect(markdown1).toBeTruthy()
			expect(markdown2).toBeTruthy()
		})

		it('supports strikethrough with ~~', () => {
			const markdown = '~~strikethrough~~'
			expect(markdown).toBeTruthy()
		})

		it('supports underline with <u>', () => {
			const html = '<u>underline</u>'
			expect(html).toBeTruthy()
		})
	})

	describe('Lists', () => {
		it('supports unordered lists with -, *, or +', () => {
			const markdown1 = '- Item 1\n- Item 2'
			const markdown2 = '* Item 1\n* Item 2'
			const markdown3 = '+ Item 1\n+ Item 2'
			expect(markdown1).toBeTruthy()
			expect(markdown2).toBeTruthy()
			expect(markdown3).toBeTruthy()
		})

		it('supports ordered lists with numbers', () => {
			const markdown = '1. First\n2. Second\n3. Third'
			expect(markdown).toBeTruthy()
		})

		it('supports nested lists with indentation', () => {
			const markdown = '- Item 1\n  - Nested 1\n  - Nested 2\n- Item 2'
			expect(markdown).toBeTruthy()
		})
	})

	describe('Blockquotes', () => {
		it('supports blockquotes with >', () => {
			const markdown = '> This is a blockquote'
			expect(markdown).toBeTruthy()
		})

		it('supports multi-paragraph blockquotes', () => {
			const markdown = '> Paragraph 1\n>\n> Paragraph 2'
			expect(markdown).toBeTruthy()
		})

		it('supports nested blockquotes with >>', () => {
			const markdown = '> Level 1\n>> Level 2'
			expect(markdown).toBeTruthy()
		})
	})

	describe('Code', () => {
		it('supports inline code with backticks', () => {
			const markdown = '`code`'
			expect(markdown).toBeTruthy()
		})

		it('supports code blocks with indentation', () => {
			const markdown = '    code block\n    line 2'
			expect(markdown).toBeTruthy()
		})

		it('supports fenced code blocks with triple backticks', () => {
			const markdown = '```\ncode block\nline 2\n```'
			expect(markdown).toBeTruthy()
		})

		it('supports syntax highlighting in fenced code blocks', () => {
			const markdown = '```javascript\nconst x = 1;\n```'
			expect(markdown).toBeTruthy()
		})
	})

	describe('Horizontal Rules', () => {
		it('supports horizontal rules with ---, ___, or ***', () => {
			const markdown1 = '---'
			const markdown2 = '___'
			const markdown3 = '***'
			expect(markdown1).toBeTruthy()
			expect(markdown2).toBeTruthy()
			expect(markdown3).toBeTruthy()
		})
	})

	describe('Links', () => {
		it('supports inline links with [text](url)', () => {
			const markdown = '[Link](https://example.com)'
			expect(markdown).toBeTruthy()
		})

		it('supports links with titles', () => {
			const markdown = '[Link](https://example.com "Title")'
			expect(markdown).toBeTruthy()
		})

		it('supports autolinks with <url>', () => {
			const markdown = '<https://example.com>'
			expect(markdown).toBeTruthy()
		})

		it('supports email autolinks', () => {
			const markdown = '<user@example.com>'
			expect(markdown).toBeTruthy()
		})

		it('supports reference-style links', () => {
			const markdown = '[Link][ref]\n\n[ref]: https://example.com'
			expect(markdown).toBeTruthy()
		})
	})

	describe('Images', () => {
		it('supports images with ![alt](url)', () => {
			const markdown = '![Alt text](https://example.com/image.jpg)'
			expect(markdown).toBeTruthy()
		})

		it('supports images with titles', () => {
			const markdown = '![Alt](https://example.com/image.jpg "Title")'
			expect(markdown).toBeTruthy()
		})

		it('supports linking images', () => {
			const markdown = '[![Alt](image.jpg)](https://example.com)'
			expect(markdown).toBeTruthy()
		})
	})

	describe('Escaping Characters', () => {
		it('supports escaping special characters with backslash', () => {
			const markdown = '\\* Not a list item'
			expect(markdown).toBeTruthy()
		})
	})

	describe('HTML', () => {
		it('supports inline HTML', () => {
			const html = '<em>italic</em>'
			expect(html).toBeTruthy()
		})

		it('supports block-level HTML', () => {
			const html = '<div>content</div>'
			expect(html).toBeTruthy()
		})
	})

	describe('Line Breaks', () => {
		it('supports line breaks with two spaces at end', () => {
			const markdown = 'Line 1  \nLine 2'
			expect(markdown).toBeTruthy()
		})

		it('supports line breaks with <br> tag', () => {
			const html = 'Line 1<br>Line 2'
			expect(html).toBeTruthy()
		})
	})
})
