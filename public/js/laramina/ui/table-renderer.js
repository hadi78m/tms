export const TableRenderer = {

    renderPagination(meta, table) {

        const container = table.container.querySelector('.admin-pagination')
        if (!container) return

        const totalPages = Math.ceil(meta.total / meta.per_page)
        const current = meta.current_page

        if (totalPages <= 1) {
            container.innerHTML = ''
            return
        }

        const start = (current - 1) * meta.per_page + 1
        const end = Math.min(current * meta.per_page, meta.total)

        const prev = Math.max(1, current - 1)
        const next = Math.min(totalPages, current + 1)

        container.innerHTML = `
        <div class="flex items-center justify-between text-sm">

            <div class="text-gray-600">
                نمایش ${start} تا ${end} از ${meta.total}
            </div>

            <div class="flex items-center gap-1">

                <button data-page="1" ${current === 1 ? 'disabled' : ''}
                    class="px-2 py-1 border rounded">«</button>

                <button data-page="${prev}" ${current === 1 ? 'disabled' : ''}
                    class="px-2 py-1 border rounded">‹</button>

                ${this.pages(current, totalPages)}

                <button data-page="${next}" ${current === totalPages ? 'disabled' : ''}
                    class="px-2 py-1 border rounded">›</button>

                <button data-page="${totalPages}" ${current === totalPages ? 'disabled' : ''}
                    class="px-2 py-1 border rounded">»</button>

                <select data-perpage class="border ml-2 px-2 py-1 rounded">
                    ${[10, 25, 50].map(n =>
            `<option value="${n}" ${meta.per_page == n ? 'selected' : ''}>${n}</option>`
        ).join('')}
                </select>

            </div>
        </div>
        `
    },

    pages(current, total) {

        let html = ''
        const start = Math.max(1, current - 2)
        const end = Math.min(total, current + 2)

        for (let i = start; i <= end; i++) {
            html += `
            <button
                data-page="${i}"
                class="px-3 py-1 border rounded
                ${i === current ? 'bg-blue-500 text-white' : ''}">
                ${i}
            </button>`
        }

        return html
    }
}
