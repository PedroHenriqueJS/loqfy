import { createTheme } from '@mui/material/styles'

const easeOut = 'cubic-bezier(0.23, 1, 0.32, 1)'
const heading = '"Outfit Variable", "Geist Variable", system-ui, sans-serif'

export const theme = createTheme({
  cssVariables: { colorSchemeSelector: 'class' },
  colorSchemes: {
    light: {
      palette: {
        primary: { main: '#2F6BEA', dark: '#2456C4', contrastText: '#fff' },
        success: { main: '#1F7A45' },
        warning: { main: '#9A5B00' },
        error: { main: '#C0332A' },
        text: { primary: '#101A33', secondary: '#5A6682' },
        background: { default: '#F3F5FA', paper: '#FFFFFF' },
        divider: '#E2E7F1',
      },
    },
    dark: {
      palette: {
        primary: { main: '#6F9AF7', dark: '#5B8DF6', contrastText: '#0A1120' },
        success: { main: '#5FC98C' },
        warning: { main: '#F2C264' },
        error: { main: '#F28B82' },
        text: { primary: '#E8ECF5', secondary: '#9AA6BF' },
        background: { default: '#0A1120', paper: '#131C30' },
        divider: '#243049',
      },
    },
  },
  shape: { borderRadius: 10 },
  typography: {
    fontFamily: '"Geist Variable", system-ui, -apple-system, "Segoe UI", sans-serif',
    h1: { fontFamily: heading, fontSize: '1.625rem', fontWeight: 600, letterSpacing: '-0.02em', lineHeight: 1.2 },
    h2: { fontFamily: heading, fontSize: '1.0625rem', fontWeight: 600, letterSpacing: '-0.01em' },
    subtitle2: { fontSize: '0.6875rem', fontWeight: 600, letterSpacing: '0.06em', textTransform: 'uppercase' },
    body2: { fontSize: '0.875rem' },
    button: { textTransform: 'none', fontWeight: 600 },
  },
  components: {
    MuiButtonBase: {
      defaultProps: { disableRipple: true },
    },
    MuiButton: {
      defaultProps: { disableElevation: true },
      styleOverrides: {
        root: {
          borderRadius: 9,
          whiteSpace: 'nowrap',
          transition: `transform 140ms ${easeOut}, background-color 150ms ease, border-color 150ms ease`,
          '&:active': { transform: 'scale(0.97)' },
        },
      },
    },
    MuiIconButton: {
      styleOverrides: {
        root: {
          transition: `transform 140ms ${easeOut}, background-color 150ms ease`,
          '&:active': { transform: 'scale(0.94)' },
        },
      },
    },
    MuiPaper: {
      defaultProps: { elevation: 0 },
      styleOverrides: { root: { backgroundImage: 'none' } },
    },
    MuiDialog: {
      styleOverrides: { paper: { borderRadius: 16, border: '1px solid var(--lq-border)' } },
    },
    MuiChip: {
      styleOverrides: { root: { fontWeight: 600 } },
    },
    MuiTableCell: {
      styleOverrides: {
        root: { borderColor: 'var(--lq-border)' },
        head: { fontSize: '0.75rem', fontWeight: 600, color: 'var(--lq-muted)', letterSpacing: '0.02em', backgroundColor: 'var(--lq-subtle)' },
      },
    },
    MuiTableRow: {
      styleOverrides: { root: { '&.MuiTableRow-hover:hover': { backgroundColor: 'var(--lq-hover)' } } },
    },
    MuiOutlinedInput: {
      styleOverrides: { root: { borderRadius: 9 } },
    },
    MuiTooltip: {
      defaultProps: { enterDelay: 400, enterNextDelay: 0 },
    },
  },
})
