/**
 * Route handler for deposit address generation endpoint.
 */

import { Request, Response } from 'express';
import { generateDepositAddress } from '../services/addressGenerator';
import { Config } from '../config';

export interface DepositAddressRequest {
  userId: number;
  network: string;
  purpose: string;
}

export interface DepositAddressResponse {
  address: string;
}

/**
 * Handle POST /api/deposit/address
 */
export async function handleDepositAddress(
  req: Request,
  res: Response,
  config: Config
): Promise<void> {
  try {
    const body = req.body as DepositAddressRequest;

    // Validate userId
    if (!body.userId || typeof body.userId !== 'number' || body.userId <= 0) {
      res.status(400).json({
        success: false,
        error: {
          code: 'INVALID_USER_ID',
          message: 'userId is required and must be a positive integer',
        },
      });
      return;
    }

    // Validate network
    if (!body.network || typeof body.network !== 'string') {
      res.status(400).json({
        success: false,
        error: {
          code: 'INVALID_NETWORK',
          message: 'network is required and must be a string',
        },
      });
      return;
    }

    const supportedNetworks = ['erc20', 'bep20', 'trc20'];
    if (!supportedNetworks.includes(body.network)) {
      res.status(400).json({
        success: false,
        error: {
          code: 'INVALID_NETWORK',
          message: `Unsupported network. Supported: ${supportedNetworks.join(', ')}`,
        },
      });
      return;
    }

    // Validate purpose
    if (!body.purpose || typeof body.purpose !== 'string' || body.purpose.trim() === '') {
      res.status(400).json({
        success: false,
        error: {
          code: 'INVALID_PURPOSE',
          message: 'purpose is required and must be a non-empty string',
        },
      });
      return;
    }

    // Generate address
    const address = generateDepositAddress(
      body.userId,
      body.network,
      body.purpose,
      config
    );

    res.json({
      address: address,
    });
  } catch (error) {
    console.error('Error generating deposit address:', error);
    res.status(500).json({
      success: false,
      error: {
        code: 'INTERNAL_ERROR',
        message: error instanceof Error ? error.message : 'An error occurred',
      },
    });
  }
}

