export function applyThemeToDom({ mode, accent }) {
  const root = document.documentElement

  root.classList.toggle('dark', mode === 'dark')
  root.dataset.accent = accent || 'slate'
}

