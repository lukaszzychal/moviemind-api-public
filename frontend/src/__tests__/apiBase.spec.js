import { describe, it, expect } from 'vitest'
import { resolveApiBase } from '@/api/base'

describe('resolveApiBase', () => {
  it('uses the relative path in development', () => {
    expect(resolveApiBase('https://api.example.com', true)).toBe('/api/v1')
  })

  it.each([
    ['https://api.example.com', 'https://api.example.com/api/v1'],
    ['https://api.example.com/', 'https://api.example.com/api/v1'],
    ['https://api.example.com/api/v1', 'https://api.example.com/api/v1'],
    ['https://api.example.com/api/v1/', 'https://api.example.com/api/v1'],
  ])('builds the production base from %s', (envValue, expected) => {
    expect(resolveApiBase(envValue, false)).toBe(expected)
  })

  it('falls back to the same origin when the variable is not set', () => {
    expect(resolveApiBase(undefined, false)).toBe('/api/v1')
    expect(resolveApiBase('', false)).toBe('/api/v1')
  })
})
